<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application\Port;

use DoleLeilao\Shared\Domain\Money;

interface AwardRepository
{
    /** Cria a arrematação e devolve o public_id. */
    public function create(int $itemId, int $bidderId, Money $amount, \DateTimeImmutable $at): string;
}
