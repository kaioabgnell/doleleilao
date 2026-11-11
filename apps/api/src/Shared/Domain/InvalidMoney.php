<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

final class InvalidMoney extends \DomainException
{
    public static function negative(int $cents): self
    {
        return new self("Valor monetário não pode ser negativo: {$cents} centavos.");
    }

    public static function unparseable(string $value): self
    {
        return new self("Valor monetário inválido: \"{$value}\".");
    }
}
