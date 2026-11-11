<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Application\Port;

use DoleLeilao\Shared\Application\PostalCodeLookupUnavailable;
use DoleLeilao\Shared\Application\PostalCodeResult;
use DoleLeilao\Shared\Domain\PostalCode;

/** Busca o endereço de um CEP para preencher o formulário. A pessoa confere e completa o número. */
interface PostalCodeLookup
{
    /**
     * Nulo quando o CEP não existe.
     *
     * @throws PostalCodeLookupUnavailable quando nenhum serviço de CEP respondeu
     */
    public function find(PostalCode $postalCode): ?PostalCodeResult;
}
