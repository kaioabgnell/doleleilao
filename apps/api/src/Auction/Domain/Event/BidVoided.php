<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

use DoleLeilao\Auction\Domain\PlacedBid;
use DoleLeilao\Shared\Domain\Money;

final readonly class BidVoided extends ItemEvent
{
    public function __construct(
        int $itemId,
        string $itemPublicId,
        int $auctionId,
        int $sequence,
        public PlacedBid $voided,
        public ?Money $currentPrice,
        public ?int $leadingBidderId,
        public Money $nextMinimum,
        public int $bidsCount,
    ) {
        parent::__construct($itemId, $itemPublicId, $auctionId, $sequence);
    }
}
