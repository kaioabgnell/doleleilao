<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

final readonly class BidOutcome
{
    public function __construct(public string $bidPublicId, public bool $replayed, public ItemState $item) {}
}
