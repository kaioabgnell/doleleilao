<?php

declare(strict_types=1);

use DoleLeilao\Auction\Domain\Auction;
use DoleLeilao\Auction\Domain\AuctionProgress;
use DoleLeilao\Auction\Domain\AuctionSettings;
use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Auction\Domain\Event\AuctionFinished;
use DoleLeilao\Auction\Domain\Event\AuctionScheduled;
use DoleLeilao\Auction\Domain\Event\AuctionStarted;
use DoleLeilao\Auction\Domain\FeePayer;
use DoleLeilao\Auction\Domain\InvalidAuction;
use DoleLeilao\Auction\Domain\ItemSnapshot;
use DoleLeilao\Auction\Domain\ItemStatus;
use DoleLeilao\Auction\Domain\NextStep;
use DoleLeilao\Shared\Domain\RuleViolations;

function t(int $seconds): DateTimeImmutable
{
    return (new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('UTC')))->modify("+{$seconds} seconds");
}

function draftLot(int $startsIn = 86_400): Auction
{
    return Auction::draft(5, '01LOTE', 9, 'Relógios de Coleção', 'relogios-de-colecao', 'Peças raras.', t($startsIn), AuctionSettings::defaults(), t(0));
}

describe('lote de leilão (RF-D01, RF-D06)', function () {
    it('nasce como rascunho', function () {
        $auction = draftLot();

        expect($auction->status())->toBe(AuctionStatus::Draft)->and($auction->name())->toBe('Relógios de Coleção');
    });

    it('exige nome e data de início no futuro', function () {
        Auction::draft(5, '01LOTE', 9, ' a ', 'a', '', t(-60), AuctionSettings::defaults(), t(0));
    })->throws(RuleViolations::class);

    it('agenda com pelo menos 1 item e publica LoteAgendado', function () {
        $auction = draftLot();
        $auction->schedule(3, true, t(10));

        expect($auction->status())->toBe(AuctionStatus::Scheduled)
            ->and($auction->releaseEvents()[0])->toBeInstanceOf(AuctionScheduled::class);
    });

    it('não agenda sem itens', function () {
        draftLot()->schedule(0, true, t(10));
    })->throws(InvalidAuction::class, 'Adicione pelo menos 1 item');

    it('não agenda com a data já passada', function () {
        draftLot(60)->schedule(1, true, t(120));
    })->throws(InvalidAuction::class, 'no futuro');

    it('não agenda sem cartão de crédito cadastrado (D-54)', function () {
        $auction = draftLot();

        expect(fn () => $auction->schedule(1, false, t(10)))->toThrow(InvalidAuction::class, 'Cadastre um cartão de crédito')
            ->and($auction->status())->toBe(AuctionStatus::Draft);
    });

    it('pode voltar para rascunho e ser cancelado antes de começar', function () {
        $auction = draftLot();
        $auction->schedule(1, true, t(10));
        $auction->unschedule();
        expect($auction->status())->toBe(AuctionStatus::Draft);

        $auction->cancel();
        expect($auction->status())->toBe(AuctionStatus::Cancelled);
    });

    it('começa sozinho na hora marcada', function () {
        $auction = draftLot(60);
        $auction->schedule(1, true, t(0));

        expect($auction->isDue(t(59)))->toBeFalse()->and($auction->isDue(t(60)))->toBeTrue();

        $auction->start(t(60));
        expect($auction->status())->toBe(AuctionStatus::Live)
            ->and($auction->startedAt())->toEqual(t(60))
            ->and($auction->releaseEvents())->sequence(
                fn ($e) => $e->toBeInstanceOf(AuctionScheduled::class),
                fn ($e) => $e->toBeInstanceOf(AuctionStarted::class),
            );
    });

    it('rascunho não começa', function () {
        draftLot()->start(t(10));
    })->throws(InvalidAuction::class);

    it('depois de começar, só muda pelos controles do pregão', function () {
        $auction = draftLot(60);
        $auction->schedule(1, true, t(0));
        $auction->start(t(60));

        $auction->update('Outro nome', '', t(9000), AuctionSettings::defaults(), t(61));
    })->throws(InvalidAuction::class, 'já começou');

    it('não cancela ao vivo e não volta para rascunho', function () {
        $auction = draftLot(60);
        $auction->schedule(1, true, t(0));
        $auction->start(t(60));
        $auction->cancel();
    })->throws(InvalidAuction::class);

    it('termina quando acabam os itens', function () {
        $auction = draftLot(60);
        $auction->schedule(1, true, t(0));
        $auction->start(t(60));
        $auction->releaseEvents();
        $auction->finish(t(200));

        expect($auction->status())->toBe(AuctionStatus::Finished)
            ->and($auction->releaseEvents()[0])->toBeInstanceOf(AuctionFinished::class);
    });
});

function finishedLot(): Auction
{
    $auction = draftLot(60);
    $auction->schedule(1, true, t(0));
    $auction->start(t(60));
    $auction->finish(t(200));

    return $auction;
}

describe('lote encerrado na loja', function () {
    it('nasce visível e pode sair e voltar para a loja', function () {
        $auction = finishedLot();
        expect($auction->isVisibleInStore())->toBeTrue();

        $auction->changeStoreVisibility(false);
        expect($auction->isVisibleInStore())->toBeFalse();

        $auction->changeStoreVisibility(true);
        expect($auction->isVisibleInStore())->toBeTrue();
    });

    it('só o encerrado escolhe se aparece na loja', function () {
        $auction = draftLot();
        $auction->schedule(1, true, t(10));
        $auction->changeStoreVisibility(false);
    })->throws(InvalidAuction::class, 'Só um lote encerrado');

    it('rascunho, cancelado e encerrado podem ser apagados', function () {
        $cancelled = draftLot();
        $cancelled->cancel();

        draftLot()->assertDeletable();
        $cancelled->assertDeletable();
        finishedLot()->assertDeletable();
    })->throwsNoExceptions();

    it('agendado ou ao vivo não é apagado', function () {
        $auction = draftLot();
        $auction->schedule(1, true, t(10));
        $auction->assertDeletable();
    })->throws(InvalidAuction::class, 'Cancele o lote antes');
});

describe('andamento do pregão (regra 8)', function () {
    $settings = AuctionSettings::fromArray(['gap_s' => 10]);

    it('com um item em pregão, espera', function () use ($settings) {
        $step = AuctionProgress::next([
            new ItemSnapshot(1, 1, ItemStatus::Open, null),
            new ItemSnapshot(2, 2, ItemStatus::Queued, null),
        ], $settings, t(0));

        expect($step)->toEqual(NextStep::wait());
    });

    it('abre o primeiro item logo que o lote começa', function () use ($settings) {
        $step = AuctionProgress::next([
            new ItemSnapshot(2, 2, ItemStatus::Queued, null),
            new ItemSnapshot(1, 1, ItemStatus::Queued, null),
        ], $settings, t(0));

        expect($step)->toEqual(NextStep::open(1));
    });

    it('respeita o intervalo entre itens', function () use ($settings) {
        $items = [
            new ItemSnapshot(1, 1, ItemStatus::ClosedSold, t(0)),
            new ItemSnapshot(2, 2, ItemStatus::Queued, null),
        ];

        expect(AuctionProgress::next($items, $settings, t(9)))->toEqual(NextStep::wait())
            ->and(AuctionProgress::next($items, $settings, t(10)))->toEqual(NextStep::open(2));
    });

    it('pulado não espera o intervalo', function () use ($settings) {
        $step = AuctionProgress::next([
            new ItemSnapshot(1, 1, ItemStatus::Skipped, t(0)),
            new ItemSnapshot(2, 2, ItemStatus::Queued, null),
        ], $settings, t(1));

        expect($step)->toEqual(NextStep::open(2));
    });

    it('sem itens na fila, encerra o lote depois do último', function () use ($settings) {
        $step = AuctionProgress::next([
            new ItemSnapshot(1, 1, ItemStatus::ClosedSold, t(0)),
            new ItemSnapshot(2, 2, ItemStatus::ClosedUnsold, t(5)),
        ], $settings, t(6));

        expect($step)->toEqual(NextStep::finish());
    });
});

describe('quem paga a taxa do lote (D-54)', function () {
    it('começa com o leiloeiro assumindo a taxa', function () {
        expect(AuctionSettings::defaults()->feePayer)->toBe(FeePayer::Auctioneer)
            ->and(AuctionSettings::defaults()->toArray()['fee_payer'])->toBe('auctioneer');
    });

    it('pode repassar a taxa ao vencedor', function () {
        expect(AuctionSettings::fromArray(['fee_payer' => 'bidder'])->feePayer)->toBe(FeePayer::Bidder);
    });

    it('recusa opção desconhecida', function () {
        AuctionSettings::fromArray(['fee_payer' => 'ninguem']);
    })->throws(InvalidAuction::class, 'Escolha quem paga a taxa do leilão.');
});
