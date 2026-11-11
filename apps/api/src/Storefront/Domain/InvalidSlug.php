<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

use DoleLeilao\Shared\Domain\RuleViolation;

final class InvalidSlug extends RuleViolation
{
    public static function format(): self
    {
        return new self('slug', 'Use de 3 a 40 caracteres: letras minúsculas, números e hífen (sem acentos nem espaços).');
    }

    public static function reserved(): self
    {
        return new self('slug', 'Este endereço é reservado. Escolha outro.');
    }

    public static function unchanged(): self
    {
        return new self('slug', 'Este já é o endereço da sua loja.');
    }

    public static function taken(): self
    {
        return new self('slug', 'Este endereço já está em uso. Escolha outro.');
    }
}
