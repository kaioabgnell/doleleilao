<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Application\Port\CatalogGateway;
use DoleLeilao\Catalog\Domain\ProductStatus;
use Illuminate\Support\Facades\DB;

/** Camada de tradução Leilão → Catálogo: o status do produto acompanha o item (RF-C05). */
final class EloquentCatalogGateway implements CatalogGateway
{
    /** @param list<int> $productIds */
    public function markInAuction(array $productIds): void
    {
        $this->set($productIds, ProductStatus::InAuction);
    }

    /** @param list<int> $productIds */
    public function release(array $productIds): void
    {
        $this->set($productIds, ProductStatus::Available);
    }

    public function markSold(int $productId): void
    {
        $this->set([$productId], ProductStatus::Sold);
    }

    public function markUnsold(int $productId): void
    {
        $this->set([$productId], ProductStatus::Unsold);
    }

    /** @param list<int> $ids */
    private function set(array $ids, ProductStatus $status): void
    {
        if ($ids !== []) {
            DB::table('products')->whereIn('id', $ids)->update(['status' => $status->value, 'updated_at' => now()->format('Y-m-d H:i:s.u')]);
        }
    }
}
