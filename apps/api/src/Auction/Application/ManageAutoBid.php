<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

use DoleLeilao\Auction\Application\Port\AuctionItemRepository;
use DoleLeilao\Auction\Application\Port\AuctionRepository;
use DoleLeilao\Auction\Application\Port\BidderGate;
use DoleLeilao\Auction\Domain\AuctionItem;
use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Application\TransactionManager;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Domain\Money;

/** Lance automático (RF-F03): cadastrar, aumentar ou desligar. */
final readonly class ManageAutoBid
{
    public function __construct(
        private TransactionManager $tx,
        private AuctionItemRepository $items,
        private AuctionRepository $auctions,
        private BidderGate $gate,
        private Clock $clock,
        private EventBus $events,
    ) {}

    public function set(int $bidderId, string $itemPublicId, int $maxCents): ItemState
    {
        $this->gate->assertCanBid($bidderId);

        return $this->mutate($itemPublicId, $bidderId, function ($item) use ($bidderId, $maxCents): void {
            $item->setAutoBid($bidderId, Money::fromCents($maxCents), $this->clock->now(), $this->auctions->settingsOf($item->auctionId));
        });
    }

    public function cancel(int $bidderId, string $itemPublicId): ItemState
    {
        return $this->mutate($itemPublicId, $bidderId, fn ($item) => $item->cancelAutoBid($bidderId));
    }

    /** @param callable(AuctionItem): void $change */
    private function mutate(string $itemPublicId, int $bidderId, callable $change): ItemState
    {
        $itemId = $this->items->idOf($itemPublicId) ?? throw new ItemNotFound;

        [$state, $events] = $this->tx->run(function () use ($itemId, $bidderId, $change): array {
            $item = $this->items->lockForUpdate($itemId);
            $change($item);
            $this->items->save($item);

            return [ItemState::of($item, $bidderId), $item->releaseEvents()];
        });

        foreach ($events as $event) {
            $this->events->publish($event);
        }

        return $state;
    }
}
