<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Application\Port;

use DoleLeilao\Billing\Domain\FeeCharge;

interface FeeChargeRepository
{
    public function existsForAuction(int $auctionId): bool;

    /** Grava o débito e as taxas de cada item. */
    public function create(FeeCharge $charge): void;
}
