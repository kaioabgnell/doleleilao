<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Domain\Event\AuctionFinished;
use DoleLeilao\Auction\Domain\Event\AuctionScheduled;
use DoleLeilao\Auction\Domain\Event\AuctionStarted;
use DoleLeilao\Auction\Domain\Event\ItemClosed;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use Illuminate\Support\Facades\DB;

/** Lista de leilões e status dos produtos mudam na loja: descarta o cache da loja no site. */
final class RevalidateStoreOnAuctionChange
{
    public function handle(AuctionScheduled|AuctionStarted|AuctionFinished|ItemClosed $event): void
    {
        $auctioneerId = $event instanceof ItemClosed
            ? DB::table('auctions')->where('id', $event->auctionId)->value('auctioneer_id')
            : $event->auctioneerId;

        if (is_numeric($auctioneerId)) {
            event(new StorefrontChanged((int) $auctioneerId));
        }
    }
}
