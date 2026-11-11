<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

final class InvalidAddress extends RuleViolation
{
    public static function postalCode(string $field): self
    {
        return new self($field, 'CEP inválido. Use os 8 números, por exemplo 01310-100.');
    }

    public static function missing(string $field, string $message): self
    {
        return new self($field, $message);
    }

    public static function tooLong(string $field, int $max): self
    {
        return new self($field, "Use no máximo {$max} caracteres.");
    }

    public static function state(string $field): self
    {
        return new self($field, 'Escolha o estado (UF).');
    }
}
