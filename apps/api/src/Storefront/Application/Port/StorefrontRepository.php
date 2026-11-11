<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application\Port;

use DoleLeilao\Storefront\Domain\Storefront;

interface StorefrontRepository
{
    public function get(int $auctioneerId): Storefront;

    public function save(Storefront $storefront): void;

    /**
     * Marca a loja como publicada se o leiloeiro está ativo, tem modelo e ainda não foi publicada.
     * Devolve o slug quando acabou de publicar; null caso contrário.
     */
    public function markPublishedIfReady(int $auctioneerId, \DateTimeImmutable $at): ?string;
}
