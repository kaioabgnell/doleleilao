<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/** `LeiloeiroCadastrado` (specs/03-dominio.md#eventos-de-domínio). */
final readonly class AuctioneerRegistered
{
    public function __construct(
        public int $auctioneerId,
        public int $ownerUserId,
    ) {}
}
