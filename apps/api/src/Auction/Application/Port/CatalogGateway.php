<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application\Port;

/** O status dos produtos acompanha o leilão (RF-C05). Implementado pela camada de catálogo. */
interface CatalogGateway
{
    /** @param list<int> $productIds */
    public function markInAuction(array $productIds): void;

    /**
     * Volta a ficar disponível (item pulado, lote cancelado ou de volta a rascunho).
     *
     * @param  list<int>  $productIds
     */
    public function release(array $productIds): void;

    public function markSold(int $productId): void;

    public function markUnsold(int $productId): void;
}
