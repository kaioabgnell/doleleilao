<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

final class InvalidPhone extends RuleViolation
{
    public static function in(string $field): self
    {
        return new self($field, 'Telefone inválido. Use DDD + número, por exemplo (11) 98765-4321.');
    }
}
