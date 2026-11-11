<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Application\Port;

use DoleLeilao\Billing\Application\SoldAuction;

/** Leitura dos itens arrematados de um lote, no contexto Leilão (camada anticorrupção). */
interface SoldItems
{
    public function ofAuction(int $auctionId): ?SoldAuction;
}
