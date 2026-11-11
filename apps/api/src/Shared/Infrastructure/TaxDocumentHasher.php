<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure;

use DoleLeilao\Shared\Domain\TaxDocument;

/**
 * Hash do CPF/CNPJ para unicidade e busca sem guardar o documento em claro (RNF-09).
 * HMAC com a APP_KEY: trocar a chave exige recalcular os hashes (e os campos criptografados).
 */
final readonly class TaxDocumentHasher
{
    public function __construct(private string $key) {}

    public function hash(TaxDocument $document): string
    {
        return hash_hmac('sha256', $document->type->value.':'.$document->value, $this->key);
    }
}
