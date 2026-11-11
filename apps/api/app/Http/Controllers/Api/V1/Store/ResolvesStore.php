<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use DoleLeilao\Storefront\Infrastructure\StorefrontResolver;

/** Rotas públicas `/storefronts/{slug}/…`: só lojas no ar; a loja vira o tenant da requisição. */
trait ResolvesStore
{
    private function resolveStore(string $slug): AuctioneerRecord
    {
        $resolved = app(StorefrontResolver::class)->resolve($slug);
        if ($resolved['state'] !== 'live') {
            abort(404, 'Não encontramos esta loja.');
        }
        app(CurrentTenant::class)->set($resolved['auctioneer']->id);

        return $resolved['auctioneer'];
    }
}
