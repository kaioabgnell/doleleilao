<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

use DoleLeilao\Auction\Application\Port\AuctionItemRepository;
use DoleLeilao\Auction\Application\Port\AuctionRepository;
use DoleLeilao\Auction\Application\Port\AuditLog;
use DoleLeilao\Auction\Domain\AuctionItem;
use DoleLeilao\Auction\Domain\InvalidAuction;
use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Application\TransactionManager;
use DoleLeilao\Shared\Domain\Clock;

/**
 * Controles do pregão no painel (RF-D05): iniciar, pausar, retomar, encerrar o item agora,
 * pular item e anular o último lance. Toda ação vai para o log de auditoria.
 */
final readonly class ControlAuction
{
    public function __construct(
        private TransactionManager $tx,
        private AuctionRepository $auctions,
        private AuctionItemRepository $items,
        private SettleClosedItem $settle,
        private AuditLog $audit,
        private Clock $clock,
        private EventBus $events,
    ) {}

    public function start(string $auctionPublicId, Actor $actor): void
    {
        $auctionId = $this->auctionId($auctionPublicId);
        $this->publish($this->tx->run(function () use ($auctionId, $actor): array {
            $now = $this->clock->now();
            $auction = $this->auctions->lockForUpdate($auctionId);
            $auction->start($now);
            $this->auctions->save($auction);
            $this->audit->record($auctionId, $actor, 'auction_started', [], $now);

            return $auction->releaseEvents();
        }));
    }

    /** Pausa o item em pregão (regra 9). Também usado pelo sistema quando o WebSocket cai. */
    public function pause(string $auctionPublicId, Actor $actor, string $reason = ''): void
    {
        $this->onRunningItem($auctionPublicId, $actor, 'item_paused', ['reason' => $reason], fn (AuctionItem $item, $now) => $item->pause($now));
    }

    public function resume(string $auctionPublicId, Actor $actor): void
    {
        $this->onRunningItem($auctionPublicId, $actor, 'item_resumed', [], fn (AuctionItem $item, $now) => $item->resume($now));
    }

    public function closeItemNow(string $itemPublicId, Actor $actor): void
    {
        $this->onItem($itemPublicId, $actor, 'item_closed_manually', [], fn (AuctionItem $item, $now) => $item->closeNow($now));
    }

    public function skipItem(string $itemPublicId, Actor $actor): void
    {
        $this->onItem($itemPublicId, $actor, 'item_skipped', [], fn (AuctionItem $item, $now) => $item->skip($now));
    }

    /** Regra 10: o motivo é obrigatório e fica no lance e no log. */
    public function voidLastBid(string $itemPublicId, string $reason, Actor $actor): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new InvalidAuction('reason', 'Informe o motivo da anulação.');
        }
        $itemId = $this->items->idOf($itemPublicId) ?? throw new ItemNotFound;

        $this->publish($this->tx->run(function () use ($itemId, $reason, $actor): array {
            $now = $this->clock->now();
            $item = $this->items->lockForUpdate($itemId);
            [$last, $previous] = array_pad($this->items->lastValidBids($itemId, 2), 2, null);
            if ($last === null) {
                throw new InvalidAuction('bid', 'Este item ainda não tem lances.');
            }
            $item->voidLastBid($last->bid, $previous?->bid, $now, $this->auctions->settingsOf($item->auctionId));
            $this->items->markVoided($last->id, $reason, $actor->id, $now);
            $this->items->save($item);
            $this->audit->record($item->auctionId, $actor, 'bid_voided', [
                'item' => $item->publicId,
                'bid' => $last->publicId,
                'amount_cents' => $last->bid->amount->cents,
                'reason' => $reason,
            ], $now);

            return $item->releaseEvents();
        }));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(AuctionItem, \DateTimeImmutable): void  $action
     */
    private function onRunningItem(string $auctionPublicId, Actor $actor, string $log, array $payload, callable $action): void
    {
        $auctionId = $this->auctionId($auctionPublicId);
        $itemId = $this->auctions->runningItemId($auctionId)
            ?? throw new InvalidAuction('item', 'Nenhum item em pregão agora.');
        $this->apply($itemId, $actor, $log, $payload, $action);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(AuctionItem, \DateTimeImmutable): void  $action
     */
    private function onItem(string $itemPublicId, Actor $actor, string $log, array $payload, callable $action): void
    {
        $this->apply($this->items->idOf($itemPublicId) ?? throw new ItemNotFound, $actor, $log, $payload, $action);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(AuctionItem, \DateTimeImmutable): void  $action
     */
    private function apply(int $itemId, Actor $actor, string $log, array $payload, callable $action): void
    {
        $this->publish($this->tx->run(function () use ($itemId, $actor, $log, $payload, $action): array {
            $now = $this->clock->now();
            $item = $this->items->lockForUpdate($itemId);
            $action($item, $now);
            $this->items->save($item);
            if ($item->isClosed()) {
                $this->auctions->setCurrentItem($item->auctionId, null);
            }
            $this->audit->record($item->auctionId, $actor, $log, ['item' => $item->publicId] + $payload, $now);

            return $this->settle->settle($item->releaseEvents());
        }));
    }

    private function auctionId(string $publicId): int
    {
        return $this->auctions->idOf($publicId) ?? throw new ItemNotFound;
    }

    /** @param list<object> $events */
    private function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->events->publish($event);
        }
    }
}
