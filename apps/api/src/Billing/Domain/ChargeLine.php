<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Domain;

/** Um item arrematado dentro do débito do lote (tabela `award_fees`). O título fica como cópia para o histórico. */
final readonly class ChargeLine
{
    public function __construct(public int $awardId, public string $itemTitle, public ItemFee $fee) {}
}
