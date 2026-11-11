<?php

declare(strict_types=1);

use DoleLeilao\Auction\Domain\AuctionItem;
use DoleLeilao\Auction\Domain\AuctionSettings;
use DoleLeilao\Auction\Domain\BidRejected;
use DoleLeilao\Auction\Domain\BidType;
use DoleLeilao\Auction\Domain\Event\AutoBidExhausted;
use DoleLeilao\Auction\Domain\Event\BidAccepted;
use DoleLeilao\Auction\Domain\Event\BidderOutbid;
use DoleLeilao\Auction\Domain\Event\BidVoided;
use DoleLeilao\Auction\Domain\Event\ItemClosed;
use DoleLeilao\Auction\Domain\Event\ItemOpened;
use DoleLeilao\Auction\Domain\Event\ItemPaused;
use DoleLeilao\Auction\Domain\Event\ItemResumed;
use DoleLeilao\Auction\Domain\InvalidAuction;
use DoleLeilao\Auction\Domain\ItemStatus;
use DoleLeilao\Auction\Domain\PlacedBid;
use DoleLeilao\Shared\Domain\Money;
use DoleLeilao\Shared\Domain\RuleViolations;

/*
 * Regras de lance (specs/03-dominio.md#regras-de-lance).
 * Item padrão: valor inicial R$ 1.000, incremento R$ 100, 60 s, prorrogação +15 s nos últimos 15 s.
 */

const ANA = 1;
const BRUNO = 2;
const CARLA = 3;

function at(int $seconds): DateTimeImmutable
{
    return (new DateTimeImmutable('2026-11-20 23:00:00', new DateTimeZone('UTC')))->modify("+{$seconds} seconds");
}

function brl(int $reais): Money
{
    return Money::fromCents($reais * 100);
}

function settings(): AuctionSettings
{
    return AuctionSettings::defaults();
}

function queuedItem(int $start = 1000, int $increment = 100): AuctionItem
{
    return AuctionItem::queue(10, '01ITEM', 7, 1, brl($start), brl($increment));
}

function openItem(int $start = 1000, int $increment = 100): AuctionItem
{
    $item = queuedItem($start, $increment);
    $item->open(at(0), settings());
    $item->releaseEvents();

    return $item;
}

/** @return list<array{int, int, string}> [quem, reais, tipo] dos lances novos, na ordem. */
function bidsOf(AuctionItem $item): array
{
    return array_map(
        static fn (PlacedBid $b): array => [$b->bidderId, intdiv($b->amount->cents, 100), $b->type->value],
        $item->releaseNewBids(),
    );
}

/** @return list<class-string> */
function eventTypes(AuctionItem $item): array
{
    return array_map(static fn (object $e): string => $e::class, $item->releaseEvents());
}

function rejection(callable $fn): BidRejected
{
    try {
        $fn();
    } catch (BidRejected $e) {
        return $e;
    }
    throw new RuntimeException('Esperava BidRejected.');
}

describe('abertura do pregão', function () {
    it('abre com o cronômetro do servidor e publica ItemOpened', function () {
        $item = queuedItem();
        $item->open(at(0), settings());

        expect($item->status())->toBe(ItemStatus::Open)
            ->and($item->endsAt())->toEqual(at(60))
            ->and($item->sequence())->toBe(1)
            ->and(eventTypes($item))->toBe([ItemOpened::class]);
    });

    it('só abre item da fila', function () {
        openItem()->open(at(1), settings());
    })->throws(InvalidAuction::class);

    it('permite ajustar valor inicial e incremento até abrir (RF-D01)', function () {
        $item = queuedItem();
        $item->adjustPricing(brl(500), brl(50));
        expect($item->startingPrice())->toEqual(brl(500))->and($item->increment())->toEqual(brl(50));

        $item->open(at(0), settings());
        $item->adjustPricing(brl(800), brl(50));
    })->throws(InvalidAuction::class);

    it('recusa incremento menor que R$ 1,00 e valor inicial zero', function () {
        queuedItem()->adjustPricing(Money::zero(), Money::fromCents(50));
    })->throws(RuleViolations::class);
});

describe('lance manual', function () {
    it('o primeiro lance precisa ser pelo menos o valor inicial', function () {
        $item = openItem();
        $e = rejection(fn () => $item->placeBid(ANA, brl(999), at(5), settings()));

        expect($e->minimum)->toEqual(brl(1000))
            ->and($e->getMessage())->toBe('O lance mínimo agora é R$ 1.000,00.');
    });

    it('aceita o valor inicial como primeiro lance', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());

        expect($item->currentPrice())->toEqual(brl(1000))
            ->and($item->leadingBidderId())->toBe(ANA)
            ->and($item->bidsCount())->toBe(1)
            ->and($item->nextMinimum())->toEqual(brl(1100))
            ->and(bidsOf($item))->toBe([[ANA, 1000, 'manual']]);
    });

    it('não exige múltiplo do incremento, só atual + incremento (D-39)', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->placeBid(BRUNO, brl(1150), at(6), settings());

        expect($item->currentPrice())->toEqual(brl(1150))->and($item->nextMinimum())->toEqual(brl(1250));

        $e = rejection(fn () => $item->placeBid(ANA, brl(1249), at(7), settings()));
        expect($e->minimum)->toEqual(brl(1250));
    });

    it('o líder não cobre o próprio lance', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());

        $e = rejection(fn () => $item->placeBid(ANA, brl(2000), at(6), settings()));
        expect($e->getMessage())->toBe('Você já está ganhando este item.');
    });

    it('em empate de valor ganha o primeiro gravado e o segundo vê o novo mínimo', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->placeBid(BRUNO, brl(1100), at(6), settings());

        $e = rejection(fn () => $item->placeBid(CARLA, brl(1100), at(6), settings()));
        expect($e->getMessage())->toBe('Outro participante deu este lance antes. O lance mínimo agora é R$ 1.200,00.')
            ->and($item->leadingBidderId())->toBe(BRUNO);
    });

    it('avisa o líder anterior que ele foi superado', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->releaseEvents();
        $item->placeBid(BRUNO, brl(1100), at(6), settings());

        $events = $item->releaseEvents();
        expect($events[0])->toBeInstanceOf(BidAccepted::class)
            ->and($events[1])->toBeInstanceOf(BidderOutbid::class)
            ->and($events[1]->bidderId)->toBe(ANA)
            ->and($events[1]->newPrice)->toEqual(brl(1100));
    });

    it('recusa lance em item fora do pregão', function (AuctionItem $item) {
        rejection(fn () => $item->placeBid(ANA, brl(5000), at(30), settings()));
        expect($item->bidsCount())->toBe(0);
    })->with([
        'na fila' => fn () => queuedItem(),
        'pausado' => function () {
            $item = openItem();
            $item->pause(at(10));

            return $item;
        },
        'encerrado' => function () {
            $item = openItem();
            $item->closeIfExpired(at(60));

            return $item;
        },
    ]);

    it('recusa lance depois do fim, mesmo antes do relógio encerrar', function () {
        $item = openItem();
        $e = rejection(fn () => $item->placeBid(ANA, brl(1000), at(60), settings()));
        expect($e->getMessage())->toBe('O tempo deste item acabou.');
    });

    it('cada evento aumenta a sequência do item', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->placeBid(BRUNO, brl(1100), at(6), settings());

        expect($item->sequence())->toBe(3);
    });
});

describe('prorrogação', function () {
    it('lance nos últimos 15 s leva o fim para agora + 15 s', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(50), settings());

        expect($item->endsAt())->toEqual(at(65));
        $event = $item->releaseEvents()[0];
        expect($event)->toBeInstanceOf(BidAccepted::class)->and($event->extended)->toBeTrue();
    });

    it('lance antes da janela não mexe no fim', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(44), settings());

        expect($item->endsAt())->toEqual(at(60))
            ->and($item->releaseEvents()[0]->extended)->toBeFalse();
    });

    it('nunca encurta o tempo restante', function () {
        $item = openItem();
        $short = AuctionSettings::fromArray(['extend_window_s' => 30, 'extend_by_s' => 10] + settings()->toArray());
        $item->placeBid(ANA, brl(1000), at(35), $short);

        expect($item->endsAt())->toEqual(at(60));
    });
});

describe('lance automático (proxy)', function () {
    it('ao cadastrar num item sem lances já dá o lance inicial', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(1500), at(5), settings());

        expect(bidsOf($item))->toBe([[ANA, 1000, 'auto']])
            ->and($item->leadingBidderId())->toBe(ANA);
    });

    it('cobre o lance manual de outro com atual + incremento', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(1500), at(5), settings());
        $item->releaseNewBids();
        $item->releaseEvents();

        $item->placeBid(BRUNO, brl(1100), at(6), settings());

        expect(bidsOf($item))->toBe([[BRUNO, 1100, 'manual'], [ANA, 1200, 'auto']])
            ->and($item->leadingBidderId())->toBe(ANA)
            ->and(eventTypes($item))->toBe([BidAccepted::class, BidderOutbid::class, BidAccepted::class, BidderOutbid::class]);
    });

    it('esgota quando o máximo não cobre atual + incremento e avisa o dono', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(1500), at(5), settings());
        $item->releaseNewBids();
        $item->releaseEvents();

        $item->placeBid(BRUNO, brl(1500), at(6), settings());

        expect(bidsOf($item))->toBe([[BRUNO, 1500, 'manual']])
            ->and($item->leadingBidderId())->toBe(BRUNO);
        $events = $item->releaseEvents();
        $exhausted = array_values(array_filter($events, fn ($e) => $e instanceof AutoBidExhausted));
        expect($exhausted)->toHaveCount(1)
            ->and($exhausted[0]->bidderId)->toBe(ANA)
            ->and($exhausted[0]->maxAmount)->toEqual(brl(1500))
            ->and($item->autoBidOf(ANA))->toBeNull();
    });

    it('com dois automáticos vence o maior máximo, por menor máximo + incremento', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(2000), at(5), settings());
        $item->setAutoBid(BRUNO, brl(1500), at(6), settings());

        expect(bidsOf($item))->toBe([[ANA, 1000, 'auto'], [BRUNO, 1500, 'auto'], [ANA, 1600, 'auto']])
            ->and($item->leadingBidderId())->toBe(ANA)
            ->and($item->currentPrice())->toEqual(brl(1600));
    });

    it('o vencedor fica limitado ao próprio máximo', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(1550), at(5), settings());
        $item->setAutoBid(BRUNO, brl(1500), at(6), settings());

        expect($item->leadingBidderId())->toBe(ANA)
            ->and($item->currentPrice())->toEqual(brl(1550));
    });

    it('o desafiante maior vence o líder automático', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(1500), at(5), settings());
        $item->setAutoBid(BRUNO, brl(3000), at(6), settings());

        expect(bidsOf($item))->toBe([[ANA, 1000, 'auto'], [ANA, 1500, 'auto'], [BRUNO, 1600, 'auto']])
            ->and($item->leadingBidderId())->toBe(BRUNO);
    });

    it('em empate de máximos vence quem cadastrou primeiro, pelo máximo', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(1500), at(5), settings());
        $item->setAutoBid(BRUNO, brl(1500), at(6), settings());

        expect(bidsOf($item))->toBe([[ANA, 1000, 'auto'], [ANA, 1500, 'auto']])
            ->and($item->leadingBidderId())->toBe(ANA)
            ->and($item->autoBidOf(BRUNO))->toBeNull();
    });

    it('cadastrado com o item na fila, entra em ação quando o item abre', function () {
        $item = queuedItem();
        $item->setAutoBid(BRUNO, brl(1500), at(-60), settings());
        $item->setAutoBid(ANA, brl(1500), at(-50), settings());
        expect($item->releaseNewBids())->toBe([]);

        $item->open(at(0), settings());

        expect(bidsOf($item))->toBe([[BRUNO, 1000, 'auto'], [BRUNO, 1500, 'auto']])
            ->and($item->leadingBidderId())->toBe(BRUNO);
    });

    it('cadeia com três automáticos termina com exatamente um líder', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(1800), at(5), settings());
        $item->setAutoBid(BRUNO, brl(2500), at(6), settings());
        $item->setAutoBid(CARLA, brl(2200), at(7), settings());

        expect($item->leadingBidderId())->toBe(BRUNO)
            ->and($item->currentPrice())->toEqual(brl(2300))
            ->and($item->autoBidOf(ANA))->toBeNull()
            ->and($item->autoBidOf(CARLA))->toBeNull();
    });

    it('o líder pode aumentar o automático sem dar lance', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(1500), at(5), settings());
        $item->releaseNewBids();
        $item->setAutoBid(ANA, brl(2500), at(6), settings());

        expect($item->releaseNewBids())->toBe([])
            ->and($item->autoBidOf(ANA)?->maxAmount)->toEqual(brl(2500));
    });

    it('recusa máximo abaixo do próximo lance mínimo', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $e = rejection(fn () => $item->setAutoBid(BRUNO, brl(1099), at(6), settings()));

        expect($e->field)->toBe('max_amount_cents')->and($e->minimum)->toEqual(brl(1100));
    });

    it('recusa diminuir um automático já cadastrado', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(2000), at(5), settings());
        $e = rejection(fn () => $item->setAutoBid(ANA, brl(1800), at(6), settings()));

        expect($e->getMessage())->toBe('O novo máximo precisa ser maior que R$ 2.000,00.');
    });

    it('cancelar o automático não desfaz os lances já dados', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(2000), at(5), settings());
        $item->cancelAutoBid(ANA);
        $item->placeBid(BRUNO, brl(1100), at(6), settings());

        expect($item->leadingBidderId())->toBe(BRUNO)->and($item->autoBidOf(ANA))->toBeNull();
    });

    it('lance automático também prorroga', function () {
        $item = openItem();
        $item->setAutoBid(ANA, brl(2000), at(5), settings());
        $item->placeBid(BRUNO, brl(1100), at(55), settings());

        expect($item->endsAt())->toEqual(at(70));
    });
});

describe('pausa e retomada', function () {
    it('congela o tempo restante e devolve ao retomar', function () {
        $item = openItem();
        $item->pause(at(20));
        expect($item->status())->toBe(ItemStatus::Paused)->and($item->pausedRemainingMs())->toBe(40_000);

        $item->resume(at(500));
        expect($item->status())->toBe(ItemStatus::Open)
            ->and($item->endsAt())->toEqual(at(540))
            ->and(eventTypes($item))->toBe([ItemPaused::class, ItemResumed::class]);
    });

    it('não encerra pelo relógio enquanto está pausado', function () {
        $item = openItem();
        $item->pause(at(20));

        expect($item->closeIfExpired(at(600)))->toBeFalse();
    });
});

describe('encerramento', function () {
    it('com lances, fecha vendido para o líder', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->releaseEvents();

        expect($item->closeIfExpired(at(59)))->toBeFalse()
            ->and($item->closeIfExpired(at(60)))->toBeTrue()
            ->and($item->status())->toBe(ItemStatus::ClosedSold);

        $closed = $item->releaseEvents()[0];
        expect($closed)->toBeInstanceOf(ItemClosed::class)
            ->and($closed->winnerId)->toBe(ANA)
            ->and($closed->amount)->toEqual(brl(1000));
    });

    it('sem lances, fecha sem venda', function () {
        $item = openItem();
        $item->closeIfExpired(at(61));

        expect($item->status())->toBe(ItemStatus::ClosedUnsold)
            ->and($item->releaseEvents()[0]->winnerId)->toBeNull();
    });

    it('o leiloeiro pode encerrar agora, mesmo pausado', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->pause(at(10));
        $item->closeNow(at(11));

        expect($item->status())->toBe(ItemStatus::ClosedSold)->and($item->closedAt())->toEqual(at(11));
    });

    it('pular descarta o pregão sem arrematação', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->skip(at(10));

        expect($item->status())->toBe(ItemStatus::Skipped)->and($item->isClosed())->toBeTrue();
    });

    it('item fechado não volta', function () {
        $item = openItem();
        $item->closeNow(at(10));
        $item->skip(at(11));
    })->throws(InvalidAuction::class);
});

describe('anulação do último lance', function () {
    it('volta ao lance válido anterior', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->placeBid(BRUNO, brl(1300), at(6), settings());
        $item->releaseEvents();

        $item->voidLastBid(
            new PlacedBid(BRUNO, brl(1300), BidType::Manual, at(6)),
            new PlacedBid(ANA, brl(1000), BidType::Manual, at(5)),
            at(8),
            settings(),
        );

        expect($item->currentPrice())->toEqual(brl(1000))
            ->and($item->leadingBidderId())->toBe(ANA)
            ->and($item->bidsCount())->toBe(1)
            ->and(eventTypes($item))->toBe([BidVoided::class]);
    });

    it('sem lance anterior, o item volta a não ter lances', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->voidLastBid(new PlacedBid(ANA, brl(1000), BidType::Manual, at(5)), null, at(8), settings());

        expect($item->currentPrice())->toBeNull()
            ->and($item->leadingBidderId())->toBeNull()
            ->and($item->nextMinimum())->toEqual(brl(1000));
    });

    it('desliga o automático de quem teve o lance anulado e recalcula os outros', function () {
        $item = openItem();
        $item->setAutoBid(CARLA, brl(1400), at(4), settings());
        $item->setAutoBid(ANA, brl(3000), at(5), settings());
        expect($item->leadingBidderId())->toBe(ANA)->and($item->currentPrice())->toEqual(brl(1500));

        $item->voidLastBid(
            new PlacedBid(ANA, brl(1500), BidType::Auto, at(5)),
            new PlacedBid(CARLA, brl(1400), BidType::Auto, at(5)),
            at(8),
            settings(),
        );

        expect($item->autoBidOf(ANA))->toBeNull()
            ->and($item->leadingBidderId())->toBe(CARLA)
            ->and($item->currentPrice())->toEqual(brl(1400));
    });

    it('só anula o lance que está na frente', function () {
        $item = openItem();
        $item->placeBid(ANA, brl(1000), at(5), settings());
        $item->voidLastBid(new PlacedBid(BRUNO, brl(900), BidType::Manual, at(5)), null, at(8), settings());
    })->throws(InvalidAuction::class);
});
