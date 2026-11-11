<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

final readonly class AuctionStarted
{
    public function __construct(
        public int $auctionId,
        public string $auctionPublicId,
        public int $auctioneerId,
        public \DateTimeImmutable $at,
    ) {}
}
