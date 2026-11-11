<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

use DoleLeilao\Shared\Domain\Money;

final readonly class ItemOpened extends ItemEvent
{
    public function __construct(
        int $itemId,
        string $itemPublicId,
        int $auctionId,
        int $sequence,
        public \DateTimeImmutable $endsAt,
        public Money $startingPrice,
        public Money $increment,
    ) {
        parent::__construct($itemId, $itemPublicId, $auctionId, $sequence);
    }
}
