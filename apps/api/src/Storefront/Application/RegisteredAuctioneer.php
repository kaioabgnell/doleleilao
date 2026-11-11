<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

final readonly class RegisteredAuctioneer
{
    public function __construct(
        public int $auctioneerId,
        public int $ownerUserId,
    ) {}
}
