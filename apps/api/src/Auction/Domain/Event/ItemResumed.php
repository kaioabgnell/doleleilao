<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

final readonly class ItemResumed extends ItemEvent
{
    public function __construct(int $itemId, string $itemPublicId, int $auctionId, int $sequence, public \DateTimeImmutable $endsAt)
    {
        parent::__construct($itemId, $itemPublicId, $auctionId, $sequence);
    }
}
