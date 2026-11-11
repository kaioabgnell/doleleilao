<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Storefront\Application\Port\AuctioneerRepository;
use DoleLeilao\Storefront\Domain\InvalidSlug;
use DoleLeilao\Storefront\Domain\SlugChangePolicy;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use DoleLeilao\Storefront\Domain\StoreSlug;

/** Troca o endereço da loja (RF-A06, D-29). O antigo redireciona com 301 por 90 dias. */
final readonly class ChangeStoreSlug
{
    public function __construct(
        private AuctioneerRepository $auctioneers,
        private Clock $clock,
        private EventBus $events,
        private SlugChangePolicy $policy = new SlugChangePolicy,
    ) {}

    public function handle(int $auctioneerId, string $newSlug): StoreSlug
    {
        $slug = StoreSlug::fromString($newSlug);
        $auctioneer = $this->auctioneers->get($auctioneerId);
        $now = $this->clock->now();

        if (! $slug->equals($auctioneer->slug()) && $this->auctioneers->slugIsTaken($slug, $auctioneerId)) {
            throw InvalidSlug::taken();
        }

        $old = $auctioneer->changeSlug($slug, $now, $this->policy);
        $this->auctioneers->saveSlugChange($auctioneer, $old, $this->policy->redirectExpiresAt($now));
        $this->events->publish(new StorefrontChanged($auctioneerId, [$old->value]));

        return $slug;
    }
}
