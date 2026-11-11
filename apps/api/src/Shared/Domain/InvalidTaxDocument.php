<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

final class InvalidTaxDocument extends RuleViolation
{
    public static function of(TaxDocumentType $type, string $field = 'document'): self
    {
        return new self($field, "{$type->label()} inválido.");
    }
}
