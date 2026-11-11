<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

use DoleLeilao\Auction\Application\Port\AuctionItemRepository;
use DoleLeilao\Auction\Application\Port\AuctionRepository;
use DoleLeilao\Auction\Application\Port\AuditLog;
use DoleLeilao\Auction\Domain\AuctionProgress;
use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Application\TransactionManager;
use DoleLeilao\Shared\Domain\Clock;

/**
 * Uma volta do relógio dos leilões (regra 8, T-03), chamada a cada 250 ms pelo `auction:clock`:
 * 1) inicia os lotes na hora marcada; 2) encerra os itens com o tempo esgotado;
 * 3) abre o próximo item depois do intervalo ou encerra o lote. Cada passo trava só o que usa
 * (SKIP LOCKED), então dois relógios nunca fazem a mesma coisa duas vezes.
 */
final readonly class RunAuctionClock
{
    private const int BATCH = 50;

    public function __construct(
        private TransactionManager $tx,
        private AuctionRepository $auctions,
        private AuctionItemRepository $items,
        private SettleClosedItem $settle,
        private AuditLog $audit,
        private Clock $clock,
        private EventBus $events,
    ) {}

    /** @return int quantas mudanças fez (0 = nada a fazer) */
    public function tick(): int
    {
        return $this->startDueAuctions() + $this->closeExpiredItems() + $this->advanceLiveAuctions();
    }

    private function startDueAuctions(): int
    {
        return $this->inTransaction(function (): array {
            $now = $this->clock->now();
            $events = [];
            foreach ($this->auctions->lockDueToStart($now, self::BATCH) as $auctionId) {
                $auction = $this->auctions->lockForUpdate($auctionId);
                $auction->start($now);
                $this->auctions->save($auction);
                $this->audit->record($auctionId, Actor::system(), 'auction_started', [], $now);
                array_push($events, ...$auction->releaseEvents());
            }

            return $events;
        });
    }

    private function closeExpiredItems(): int
    {
        return $this->inTransaction(function (): array {
            $now = $this->clock->now();
            $events = [];
            foreach ($this->items->lockExpired($now, self::BATCH) as $item) {
                if (! $item->closeIfExpired($now)) {
                    continue;
                }
                $this->items->save($item);
                $this->auctions->setCurrentItem($item->auctionId, null);
                $this->audit->record($item->auctionId, Actor::system(), 'item_closed', [
                    'item' => $item->publicId,
                    'result' => $item->status()->value,
                    'amount_cents' => $item->currentPrice()?->cents,
                ], $now);
                array_push($events, ...$this->settle->settle($item->releaseEvents()));
            }

            return $events;
        });
    }

    private function advanceLiveAuctions(): int
    {
        return $this->inTransaction(function (): array {
            $now = $this->clock->now();
            $events = [];
            foreach ($this->auctions->lockLiveWithoutRunningItem(self::BATCH) as $auctionId) {
                $settings = $this->auctions->settingsOf($auctionId);
                $step = AuctionProgress::next($this->auctions->itemSnapshots($auctionId), $settings, $now);

                if ($step->action === 'open' && $step->itemId !== null) {
                    $item = $this->items->lockForUpdate($step->itemId);
                    $item->open($now, $settings);
                    $this->items->save($item);
                    $this->auctions->setCurrentItem($auctionId, $item->id);
                    $this->audit->record($auctionId, Actor::system(), 'item_opened', ['item' => $item->publicId], $now);
                    array_push($events, ...$item->releaseEvents());
                } elseif ($step->action === 'finish') {
                    $auction = $this->auctions->lockForUpdate($auctionId);
                    $auction->finish($now);
                    $this->auctions->save($auction);
                    $this->audit->record($auctionId, Actor::system(), 'auction_finished', [], $now);
                    array_push($events, ...$auction->releaseEvents());
                }
            }

            return $events;
        });
    }

    /** @param callable(): list<object> $step */
    private function inTransaction(callable $step): int
    {
        $events = $this->tx->run($step);
        foreach ($events as $event) {
            $this->events->publish($event);
        }

        return count($events);
    }
}
