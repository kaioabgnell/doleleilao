<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Storefront\Application\Port\StorefrontRepository;
use DoleLeilao\Storefront\Domain\StorefrontPublished;

/**
 * A loja fica pública com o e-mail validado e o modelo escolhido (RF-A04), em qualquer ordem.
 * Na primeira vez, grava `published_at` e publica `StorefrontPublished` ("Sua loja está no ar").
 */
final readonly class PublishStorefrontIfReady
{
    public function __construct(
        private StorefrontRepository $storefronts,
        private Clock $clock,
        private EventBus $events,
    ) {}

    public function handle(int $auctioneerId): void
    {
        $slug = $this->storefronts->markPublishedIfReady($auctioneerId, $this->clock->now());
        if ($slug !== null) {
            $this->events->publish(new StorefrontPublished($auctioneerId, $slug));
        }
    }
}
