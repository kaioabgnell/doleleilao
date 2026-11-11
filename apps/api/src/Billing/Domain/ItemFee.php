<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Domain;

use DoleLeilao\Shared\Domain\Money;

/** Taxa calculada para um item arrematado: o valor de base, a alíquota aplicada e o resultado. */
final readonly class ItemFee
{
    public function __construct(public Money $base, public int $rateBps, public Money $fee) {}
}
