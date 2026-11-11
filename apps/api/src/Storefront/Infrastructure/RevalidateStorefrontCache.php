<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure;

use DoleLeilao\Storefront\Domain\StorefrontChanged;
use DoleLeilao\Storefront\Domain\StorefrontPublished;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;

/**
 * Pede ao site (Next.js) para descartar o cache da loja (tag `storefront:{slug}`), specs/04 frontend.
 * Roda na fila; sem REVALIDATE_SECRET configurado (testes), não faz nada.
 */
final class RevalidateStorefrontCache implements ShouldQueue
{
    public int $tries = 3;

    public function handle(StorefrontChanged|StorefrontPublished $event): void
    {
        $secret = config()->string('doleleilao.revalidate_secret', '');
        if ($secret === '') {
            return;
        }

        $slug = AuctioneerRecord::query()->find($event->auctioneerId)?->slug;
        $slugs = array_values(array_unique(array_filter([
            $slug,
            ...($event instanceof StorefrontChanged ? $event->extraSlugs : []),
        ])));
        if ($slugs === []) {
            return;
        }

        Http::timeout(5)
            ->withHeaders(['X-Revalidate-Secret' => $secret])
            ->post(rtrim(config()->string('doleleilao.frontend_url'), '/').'/api/revalidate', [
                'tags' => array_map(static fn (string $s): string => "storefront:{$s}", $slugs),
            ])
            ->throw();
    }
}
