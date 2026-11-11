<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

use DoleLeilao\Shared\Domain\RuleViolation;

final class InvalidStoreTerms extends RuleViolation
{
    public static function because(string $message): self
    {
        return new self('terms', $message);
    }
}
