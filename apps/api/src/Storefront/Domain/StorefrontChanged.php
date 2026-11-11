<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/** Algo visível na loja pública mudou: o site precisa atualizar o cache dela. */
final readonly class StorefrontChanged
{
    /** @param list<string> $extraSlugs endereços antigos que também precisam ser atualizados */
    public function __construct(
        public int $auctioneerId,
        public array $extraSlugs = [],
    ) {}
}
