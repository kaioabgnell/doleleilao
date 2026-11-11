<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Application;

use DoleLeilao\Shared\Domain\PostalCode;

/**
 * O que o serviço de CEP sabe do endereço. CEP geral de cidade pequena vem sem rua e sem bairro
 * (string vazia): a pessoa preenche.
 */
final readonly class PostalCodeResult
{
    public function __construct(
        public PostalCode $postalCode,
        public string $street,
        public string $district,
        public string $city,
        public string $state,
    ) {}
}
