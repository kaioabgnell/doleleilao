<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Application;

use DoleLeilao\Shared\Domain\Money;

/** Um item arrematado: a arrematação, o título e o valor, na hora em que foi vendido. */
final readonly class SoldItem
{
    public function __construct(
        public int $awardId,
        public string $title,
        public Money $amount,
        public \DateTimeImmutable $awardedAt,
    ) {}
}
