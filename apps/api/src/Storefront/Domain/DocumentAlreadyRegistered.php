<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

use DoleLeilao\Shared\Domain\RuleViolation;
use DoleLeilao\Shared\Domain\TaxDocumentType;

final class DocumentAlreadyRegistered extends RuleViolation
{
    public function __construct(TaxDocumentType $type)
    {
        parent::__construct('document', "Este {$type->label()} já tem uma loja cadastrada.");
    }
}
