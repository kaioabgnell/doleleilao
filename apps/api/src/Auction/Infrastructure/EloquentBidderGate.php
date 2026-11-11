<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Application\BidderNotAllowed;
use DoleLeilao\Auction\Application\Port\BidderGate;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use Illuminate\Support\Facades\DB;

final class EloquentBidderGate implements BidderGate
{
    public function assertCanBid(int $bidderId): void
    {
        $bidder = BidderUser::query()->find($bidderId) ?? throw BidderNotAllowed::incomplete();
        if (self::isBanned($bidder->auctioneer_id, $bidderId)) {
            throw BidderNotAllowed::banned();
        }
        if (! $bidder->isEligible()) {
            throw BidderNotAllowed::incomplete();
        }
    }

    public static function isBanned(int $auctioneerId, int $bidderId): bool
    {
        return DB::table('bans')
            ->where('auctioneer_id', $auctioneerId)
            ->where('bidder_id', $bidderId)
            ->whereNull('lifted_at')
            ->exists();
    }
}
