<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

enum TaxDocumentType: string
{
    case Cpf = 'cpf';
    case Cnpj = 'cnpj';

    public function label(): string
    {
        return match ($this) {
            self::Cpf => 'CPF',
            self::Cnpj => 'CNPJ',
        };
    }
}
