<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure;

use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Storefront\Domain\AuctioneerStatus;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use DoleLeilao\Storefront\Infrastructure\Eloquent\SlugRedirectRecord;

/**
 * De um endereço (`/{slug}`) para a loja: no ar, mudou de endereço (RF-A06) ou não existe.
 * Loja sem e-mail validado, sem modelo ou suspensa conta como inexistente para o público.
 */
final readonly class StorefrontResolver
{
    public function __construct(private Clock $clock) {}

    /** @return array{state: 'live', auctioneer: AuctioneerRecord}|array{state: 'moved', slug: string}|array{state: 'missing'} */
    public function resolve(string $slug): array
    {
        $slug = strtolower($slug);
        $auctioneer = AuctioneerRecord::query()->with('storefront')->where('slug', $slug)->first();

        if ($auctioneer !== null) {
            $live = $auctioneer->status === AuctioneerStatus::Active && $auctioneer->storefront?->template !== null;

            return $live ? ['state' => 'live', 'auctioneer' => $auctioneer] : ['state' => 'missing'];
        }

        $redirect = SlugRedirectRecord::query()
            ->where('old_slug', $slug)
            ->where('expires_at', '>', $this->clock->now())
            ->first();
        $newSlug = $redirect === null ? null : AuctioneerRecord::query()->find($redirect->auctioneer_id)?->slug;

        return $newSlug === null ? ['state' => 'missing'] : ['state' => 'moved', 'slug' => $newSlug];
    }
}
