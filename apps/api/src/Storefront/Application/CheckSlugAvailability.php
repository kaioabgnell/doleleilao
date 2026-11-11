<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

use DoleLeilao\Storefront\Application\Port\AuctioneerRepository;
use DoleLeilao\Storefront\Domain\InvalidSlug;
use DoleLeilao\Storefront\Domain\StoreSlug;

/** Checagem ao vivo no formulário de cadastro e na troca de endereço (RF-A02, RF-A06). */
final readonly class CheckSlugAvailability
{
    public function __construct(private AuctioneerRepository $auctioneers) {}

    public function handle(string $input, ?int $forAuctioneerId = null): SlugAvailability
    {
        $normalized = strtolower(trim($input));

        try {
            $slug = StoreSlug::fromString($input);
        } catch (InvalidSlug $e) {
            $reason = in_array($normalized, StoreSlug::RESERVED, true) ? 'reserved' : 'invalid';

            return new SlugAvailability($normalized, false, $reason, $e->getMessage());
        }

        if ($this->auctioneers->slugIsTaken($slug, $forAuctioneerId)) {
            return new SlugAvailability($slug->value, false, 'taken', InvalidSlug::taken()->getMessage());
        }

        return new SlugAvailability($slug->value, true);
    }
}
