<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/** `LojaPublicada`: a loja ficou pública pela primeira vez (e-mail validado + modelo escolhido). */
final readonly class StorefrontPublished
{
    public function __construct(
        public int $auctioneerId,
        public string $slug,
    ) {}
}
