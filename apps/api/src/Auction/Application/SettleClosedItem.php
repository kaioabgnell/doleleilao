<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

use DoleLeilao\Auction\Application\Port\AuctionItemRepository;
use DoleLeilao\Auction\Application\Port\AwardRepository;
use DoleLeilao\Auction\Application\Port\CatalogGateway;
use DoleLeilao\Auction\Domain\Event\ItemAwarded;
use DoleLeilao\Auction\Domain\Event\ItemClosed;
use DoleLeilao\Auction\Domain\ItemStatus;

/**
 * Consequências de um item encerrado (relógio ou leiloeiro), na mesma transação:
 * arrematação + status do produto (vendido, sem venda ou de volta a disponível).
 */
final readonly class SettleClosedItem
{
    public function __construct(
        private AuctionItemRepository $items,
        private AwardRepository $awards,
        private CatalogGateway $catalog,
    ) {}

    /**
     * @param  list<object>  $events  eventos do item
     * @return list<object> eventos + ItemAwarded, se houve venda
     */
    public function settle(array $events): array
    {
        foreach ($events as $event) {
            if (! $event instanceof ItemClosed) {
                continue;
            }
            $productId = $this->items->productIdOf($event->itemId);
            if ($event->result === ItemStatus::ClosedSold && $event->winnerId !== null && $event->amount !== null) {
                $award = $this->awards->create($event->itemId, $event->winnerId, $event->amount, $event->closedAt);
                $events[] = new ItemAwarded($event->auctionId, $event->itemId, $event->itemPublicId, $award, $event->winnerId, $event->amount, $event->closedAt);
                if ($productId !== null) {
                    $this->catalog->markSold($productId);
                }
            } elseif ($productId !== null) {
                $event->result === ItemStatus::Skipped ? $this->catalog->release([$productId]) : $this->catalog->markUnsold($productId);
            }
        }

        return $events;
    }
}
