<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

use DoleLeilao\Auction\Domain\PlacedBid;

final readonly class StoredBid
{
    public function __construct(public int $id, public string $publicId, public PlacedBid $bid) {}
}
