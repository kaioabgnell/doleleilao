<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Application\Port;

use DoleLeilao\Billing\Domain\FeeSchedule;

/** Faixas da taxa (tabela `fee_tiers`), de todas as vigências. */
interface FeeTierRepository
{
    public function schedule(): FeeSchedule;
}
