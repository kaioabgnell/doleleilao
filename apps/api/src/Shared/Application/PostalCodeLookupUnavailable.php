<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Application;

final class PostalCodeLookupUnavailable extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Não conseguimos buscar o CEP agora. Preencha o endereço abaixo.');
    }
}
