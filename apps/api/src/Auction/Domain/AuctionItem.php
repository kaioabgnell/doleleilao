<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

use DoleLeilao\Auction\Domain\Event\AutoBidExhausted;
use DoleLeilao\Auction\Domain\Event\BidAccepted;
use DoleLeilao\Auction\Domain\Event\BidderOutbid;
use DoleLeilao\Auction\Domain\Event\BidVoided;
use DoleLeilao\Auction\Domain\Event\ItemClosed;
use DoleLeilao\Auction\Domain\Event\ItemEvent;
use DoleLeilao\Auction\Domain\Event\ItemOpened;
use DoleLeilao\Auction\Domain\Event\ItemPaused;
use DoleLeilao\Auction\Domain\Event\ItemResumed;
use DoleLeilao\Shared\Domain\Money;
use DoleLeilao\Shared\Domain\RuleViolations;

/**
 * Item de um lote: a unidade de concorrência (specs/03-dominio.md#auctionitem-item).
 * Um único escritor por vez (trava pessimista no repositório). Aqui ficam só as regras de lance,
 * sem banco e sem framework. Os lances novos e os eventos ficam pendentes até o repositório gravar.
 */
final class AuctionItem
{
    public const int MIN_INCREMENT_CENTS = 100;

    /** @var list<PlacedBid> */
    private array $newBids = [];

    /** @var list<ItemEvent> */
    private array $events = [];

    /** @param array<int, AutoBid> $autoBids por bidderId (inclui os desativados nesta operação) */
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $auctionId,
        public readonly int $position,
        private ItemStatus $status,
        private Money $startingPrice,
        private Money $increment,
        private ?Money $currentPrice = null,
        private ?int $leadingBidderId = null,
        private int $bidsCount = 0,
        private ?\DateTimeImmutable $openedAt = null,
        private ?\DateTimeImmutable $endsAt = null,
        private ?int $pausedRemainingMs = null,
        private ?\DateTimeImmutable $closedAt = null,
        private int $sequence = 0,
        private array $autoBids = [],
    ) {}

    public static function queue(int $id, string $publicId, int $auctionId, int $position, Money $startingPrice, Money $increment): self
    {
        self::assertPricing($startingPrice, $increment);

        return new self($id, $publicId, $auctionId, $position, ItemStatus::Queued, $startingPrice, $increment);
    }

    /* ---------- leitura ---------- */

    public function status(): ItemStatus
    {
        return $this->status;
    }

    public function startingPrice(): Money
    {
        return $this->startingPrice;
    }

    public function increment(): Money
    {
        return $this->increment;
    }

    public function currentPrice(): ?Money
    {
        return $this->currentPrice;
    }

    public function leadingBidderId(): ?int
    {
        return $this->leadingBidderId;
    }

    public function bidsCount(): int
    {
        return $this->bidsCount;
    }

    public function openedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function endsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function pausedRemainingMs(): ?int
    {
        return $this->pausedRemainingMs;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    /** Regra 3: sem lances, o valor inicial. Com lances, atual + incremento (não precisa ser múltiplo, D-39). */
    public function nextMinimum(): Money
    {
        return $this->currentPrice === null ? $this->startingPrice : $this->currentPrice->add($this->increment);
    }

    public function autoBidOf(int $bidderId): ?AutoBid
    {
        $auto = $this->autoBids[$bidderId] ?? null;

        return $auto !== null && $auto->active ? $auto : null;
    }

    /** @return list<AutoBid> todos, inclusive os desativados agora (o repositório grava o estado). */
    public function autoBids(): array
    {
        return array_values($this->autoBids);
    }

    /** @return list<PlacedBid> */
    public function releaseNewBids(): array
    {
        $bids = $this->newBids;
        $this->newBids = [];

        return $bids;
    }

    /** @return list<ItemEvent> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    /* ---------- cadastro ---------- */

    /** O leiloeiro ajusta os valores copiados do produto até o item abrir (RF-D01). */
    public function adjustPricing(Money $startingPrice, Money $increment): void
    {
        if ($this->status !== ItemStatus::Queued) {
            throw new InvalidAuction('starting_price_cents', 'Os valores não mudam depois que o item abre.');
        }
        self::assertPricing($startingPrice, $increment);
        $this->startingPrice = $startingPrice;
        $this->increment = $increment;
    }

    /* ---------- pregão ---------- */

    public function open(\DateTimeImmutable $now, AuctionSettings $settings): void
    {
        if ($this->status !== ItemStatus::Queued) {
            throw new InvalidAuction('item', 'Este item já passou pelo pregão.');
        }
        $this->status = ItemStatus::Open;
        $this->openedAt = $now;
        $this->endsAt = $now->modify("+{$settings->itemDurationS} seconds");
        $this->sequence++;
        $this->events[] = new ItemOpened(...$this->ref(), endsAt: $this->endsAt, startingPrice: $this->startingPrice, increment: $this->increment);

        // Automáticos cadastrados com o item na fila entram em ação agora.
        $this->resolveAutoBids($now, $settings);
    }

    /** Lance manual (regras 1 a 5 e 7). A elegibilidade do participante é checada no caso de uso. */
    public function placeBid(int $bidderId, Money $amount, \DateTimeImmutable $now, AuctionSettings $settings, ?string $idempotencyKey = null): void
    {
        $this->assertAcceptingBids($now);
        if ($this->leadingBidderId === $bidderId) {
            throw new BidRejected('Você já está ganhando este item.', $this->nextMinimum());
        }
        $minimum = $this->nextMinimum();
        if ($amount->cents < $minimum->cents) {
            throw BidRejected::belowMinimum($minimum, $this->currentPrice !== null && $amount->cents === $this->currentPrice->cents);
        }

        $this->accept(new PlacedBid($bidderId, $amount, BidType::Manual, $now, $idempotencyKey), $settings);
        $this->resolveAutoBids($now, $settings);
    }

    /**
     * Cadastra ou aumenta o lance automático (regra 6). Num item aberto, já dá o lance inicial se a
     * pessoa não estiver ganhando. Na fila, espera a abertura.
     */
    public function setAutoBid(int $bidderId, Money $max, \DateTimeImmutable $now, AuctionSettings $settings): void
    {
        if ($this->status !== ItemStatus::Queued && $this->status !== ItemStatus::Open) {
            throw new BidRejected('O lance automático só pode ser cadastrado antes ou durante o pregão do item.', field: 'max_amount_cents');
        }
        if ($this->status === ItemStatus::Open && $this->endsAt !== null && $now >= $this->endsAt) {
            throw new BidRejected('O tempo deste item acabou.', field: 'max_amount_cents');
        }

        $existing = $this->autoBidOf($bidderId);
        if ($existing !== null && $max->cents <= $existing->maxAmount->cents) {
            throw new BidRejected("O novo máximo precisa ser maior que {$existing->maxAmount->format()}.", $existing->maxAmount, 'max_amount_cents');
        }
        if ($this->leadingBidderId === $bidderId) {
            if ($this->currentPrice !== null && $max->cents <= $this->currentPrice->cents) {
                throw new BidRejected("O máximo precisa ser maior que o seu lance atual ({$this->currentPrice->format()}).", $this->nextMinimum(), 'max_amount_cents');
            }
        } elseif ($max->cents < $this->nextMinimum()->cents) {
            throw new BidRejected("O máximo precisa ser de pelo menos {$this->nextMinimum()->format()}.", $this->nextMinimum(), 'max_amount_cents');
        }

        if (isset($this->autoBids[$bidderId])) {
            $this->autoBids[$bidderId]->raiseTo($max, $now);
        } else {
            $this->autoBids[$bidderId] = new AutoBid($bidderId, $max, $now);
        }

        if ($this->status === ItemStatus::Open) {
            $this->resolveAutoBids($now, $settings);
        }
    }

    /** Desliga o automático. Os lances já dados continuam valendo. */
    public function cancelAutoBid(int $bidderId): void
    {
        $this->autoBidOf($bidderId)?->deactivate();
    }

    public function pause(\DateTimeImmutable $now): void
    {
        if ($this->status !== ItemStatus::Open || $this->endsAt === null) {
            throw new InvalidAuction('item', 'Só dá para pausar um item em pregão.');
        }
        $this->pausedRemainingMs = max(0, self::ms($this->endsAt) - self::ms($now));
        $this->status = ItemStatus::Paused;
        $this->endsAt = null;
        $this->sequence++;
        $this->events[] = new ItemPaused(...$this->ref(), remainingMs: $this->pausedRemainingMs);
    }

    /** Regra 9: ao retomar, fim = agora + tempo restante. */
    public function resume(\DateTimeImmutable $now): void
    {
        if ($this->status !== ItemStatus::Paused) {
            throw new InvalidAuction('item', 'Este item não está pausado.');
        }
        $remaining = $this->pausedRemainingMs ?? 0;
        $this->endsAt = self::addMs($now, $remaining);
        $this->pausedRemainingMs = null;
        $this->status = ItemStatus::Open;
        $this->sequence++;
        $this->events[] = new ItemResumed(...$this->ref(), endsAt: $this->endsAt);
    }

    /** Relógio do servidor (regra 8): fecha só se o tempo acabou. Devolve se fechou. */
    public function closeIfExpired(\DateTimeImmutable $now): bool
    {
        if ($this->status !== ItemStatus::Open || $this->endsAt === null || $now < $this->endsAt) {
            return false;
        }
        $this->close($now);

        return true;
    }

    /** "Encerrar item agora" (RF-D05): vende para o líder, se houver. */
    public function closeNow(\DateTimeImmutable $now): void
    {
        if (! $this->status->isRunning()) {
            throw new InvalidAuction('item', 'Só dá para encerrar um item em pregão.');
        }
        $this->close($now);
    }

    /** "Pular item" (RF-D05): sai do pregão sem arrematação. */
    public function skip(\DateTimeImmutable $now): void
    {
        if ($this->status->isClosed()) {
            throw new InvalidAuction('item', 'Este item já foi encerrado.');
        }
        $this->finish(ItemStatus::Skipped, $now, null, null);
    }

    /**
     * Regra 10: anula o lance que está na frente. O preço volta ao lance válido anterior
     * (`$previous`, ou nenhum), o automático de quem teve o lance anulado é desligado e os
     * outros automáticos são recalculados.
     */
    public function voidLastBid(PlacedBid $last, ?PlacedBid $previous, \DateTimeImmutable $now, AuctionSettings $settings): void
    {
        if (! $this->status->isRunning()) {
            throw new InvalidAuction('bid', 'Só dá para anular lances de um item em pregão.');
        }
        if ($this->currentPrice === null || $this->leadingBidderId !== $last->bidderId || ! $this->currentPrice->equals($last->amount)) {
            throw new InvalidAuction('bid', 'Só o último lance válido pode ser anulado.');
        }

        $this->currentPrice = $previous?->amount;
        $this->leadingBidderId = $previous?->bidderId;
        $this->bidsCount = max(0, $this->bidsCount - 1);
        $this->autoBidOf($last->bidderId)?->deactivate();
        $this->sequence++;
        $this->events[] = new BidVoided(
            ...$this->ref(),
            voided: $last,
            currentPrice: $this->currentPrice,
            leadingBidderId: $this->leadingBidderId,
            nextMinimum: $this->nextMinimum(),
            bidsCount: $this->bidsCount,
        );

        if ($this->status === ItemStatus::Open) {
            $this->resolveAutoBids($now, $settings);
        }
    }

    /* ---------- interno ---------- */

    private function assertAcceptingBids(\DateTimeImmutable $now): void
    {
        if ($this->status === ItemStatus::Paused) {
            throw new BidRejected('O pregão está pausado. Aguarde o leiloeiro retomar.');
        }
        if ($this->status !== ItemStatus::Open || $this->endsAt === null) {
            throw new BidRejected('Este item não está em pregão.');
        }
        if ($now >= $this->endsAt) {
            throw new BidRejected('O tempo deste item acabou.');
        }
    }

    private function accept(PlacedBid $bid, AuctionSettings $settings): void
    {
        $previousLeader = $this->leadingBidderId;
        $this->currentPrice = $bid->amount;
        $this->leadingBidderId = $bid->bidderId;
        $this->bidsCount++;
        $this->sequence++;

        // Regra 7: prorrogação nos segundos finais (nunca encurta).
        $extended = false;
        if ($this->endsAt !== null && self::ms($this->endsAt) - self::ms($bid->placedAt) < $settings->extendWindowS * 1000) {
            $candidate = $bid->placedAt->modify("+{$settings->extendByS} seconds");
            if ($candidate > $this->endsAt) {
                $this->endsAt = $candidate;
                $extended = true;
            }
        }

        $this->newBids[] = $bid;
        $this->events[] = new BidAccepted(
            ...$this->ref(),
            bid: $bid,
            nextMinimum: $this->nextMinimum(),
            endsAt: $this->endsAt ?? $bid->placedAt,
            extended: $extended,
            bidsCount: $this->bidsCount,
        );

        if ($previousLeader !== null && $previousLeader !== $bid->bidderId) {
            $this->events[] = new BidderOutbid(...$this->ref(), bidderId: $previousLeader, newPrice: $bid->amount);
        }
    }

    /**
     * Regra 6, em saltos: a cada volta, o automático mais forte que não está ganhando enfrenta o
     * automático do líder (se houver). Quem perde esgota. Termina quando ninguém mais consegue cobrir.
     * Cada volta esgota um automático ou troca o líder, então o laço é curto.
     */
    private function resolveAutoBids(\DateTimeImmutable $now, AuctionSettings $settings): void
    {
        for ($guard = 0; $guard < 1000; $guard++) {
            $minimum = $this->nextMinimum();

            $challenger = null;
            foreach ($this->autoBids as $auto) {
                if (! $auto->active || $auto->bidderId === $this->leadingBidderId) {
                    continue;
                }
                if ($auto->maxAmount->cents < $minimum->cents) {
                    $auto->deactivate();
                    $this->events[] = new AutoBidExhausted(...$this->ref(), bidderId: $auto->bidderId, maxAmount: $auto->maxAmount);

                    continue;
                }
                if ($challenger === null || $auto->beats($challenger)) {
                    $challenger = $auto;
                }
            }
            if ($challenger === null) {
                return;
            }

            $defender = $this->leadingBidderId === null ? null : $this->autoBidOf($this->leadingBidderId);
            if ($defender === null) {
                $this->accept(new PlacedBid($challenger->bidderId, $minimum, BidType::Auto, $now), $settings);

                continue;
            }

            $tie = $challenger->maxAmount->equals($defender->maxAmount);
            [$winner, $loser] = $challenger->beats($defender) ? [$challenger, $defender] : [$defender, $challenger];
            $current = $this->currentPrice->cents ?? 0;

            if ($tie) {
                // Máximos iguais: vence quem cadastrou primeiro, pelo próprio máximo.
                if ($winner === $defender && $defender->maxAmount->cents > $current) {
                    $this->accept(new PlacedBid($defender->bidderId, $defender->maxAmount, BidType::Auto, $now), $settings);
                } elseif ($winner === $challenger) {
                    $this->accept(new PlacedBid($challenger->bidderId, $challenger->maxAmount, BidType::Auto, $now), $settings);
                }

                continue;
            }

            // O perdedor vai até o próprio máximo, e o vencedor cobre por máximo do perdedor + incremento,
            // limitado ao próprio máximo.
            if ($loser->maxAmount->cents > $current) {
                $this->accept(new PlacedBid($loser->bidderId, $loser->maxAmount, BidType::Auto, $now), $settings);
            }
            $target = min($this->nextMinimum()->cents, $winner->maxAmount->cents);
            $this->accept(new PlacedBid($winner->bidderId, Money::fromCents($target), BidType::Auto, $now), $settings);
        }
    }

    private function close(\DateTimeImmutable $now): void
    {
        $sold = $this->leadingBidderId !== null && $this->currentPrice !== null;
        $this->finish($sold ? ItemStatus::ClosedSold : ItemStatus::ClosedUnsold, $now, $this->leadingBidderId, $this->currentPrice);
    }

    private function finish(ItemStatus $result, \DateTimeImmutable $now, ?int $winnerId, ?Money $amount): void
    {
        $this->status = $result;
        $this->closedAt = $now;
        $this->endsAt = null;
        $this->pausedRemainingMs = null;
        foreach ($this->autoBids as $auto) {
            $auto->deactivate();
        }
        $this->sequence++;
        $this->events[] = new ItemClosed(...$this->ref(), result: $result, winnerId: $winnerId, amount: $amount, closedAt: $now);
    }

    /** @return array{itemId: int, itemPublicId: string, auctionId: int, sequence: int} */
    private function ref(): array
    {
        return ['itemId' => $this->id, 'itemPublicId' => $this->publicId, 'auctionId' => $this->auctionId, 'sequence' => $this->sequence];
    }

    private static function assertPricing(Money $startingPrice, Money $increment): void
    {
        $errors = [];
        if ($startingPrice->cents <= 0) {
            $errors[] = new InvalidAuction('starting_price_cents', 'O valor inicial do lance precisa ser maior que zero.');
        }
        if ($increment->cents < self::MIN_INCREMENT_CENTS) {
            $errors[] = new InvalidAuction('increment_cents', 'O incremento mínimo é R$ 1,00.');
        }
        if (count($errors) === 1) {
            throw $errors[0];
        }
        if ($errors !== []) {
            throw new RuleViolations($errors);
        }
    }

    private static function ms(\DateTimeImmutable $at): int
    {
        return (int) $at->format('Uv');
    }

    private static function addMs(\DateTimeImmutable $at, int $ms): \DateTimeImmutable
    {
        return $at->modify(sprintf('+%d milliseconds', $ms));
    }
}
