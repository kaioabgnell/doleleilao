<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

use DoleLeilao\Shared\Domain\RuleViolation;

final class SlugChangeTooSoon extends RuleViolation
{
    public function __construct(public readonly \DateTimeImmutable $allowedAt)
    {
        parent::__construct('slug', 'O endereço da loja só pode ser trocado 1 vez a cada 30 dias.');
    }
}
