<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Application\Port\AwardRepository;
use DoleLeilao\Auction\Infrastructure\Eloquent\AwardRecord;
use DoleLeilao\Shared\Domain\Money;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Support\Facades\DB;

final class EloquentAwardRepository implements AwardRepository
{
    public function create(int $itemId, int $bidderId, Money $amount, \DateTimeImmutable $at): string
    {
        $award = AwardRecord::withoutGlobalScope('tenant')->create([
            'auctioneer_id' => Scalar::int(DB::table('auction_items')->where('id', $itemId)->value('auctioneer_id')),
            'auction_item_id' => $itemId,
            'bidder_id' => $bidderId,
            'amount' => $amount->cents,
            'awarded_at' => $at,
        ]);

        return $award->public_id;
    }
}
