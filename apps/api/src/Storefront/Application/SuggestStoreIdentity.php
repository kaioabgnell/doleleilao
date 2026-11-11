<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

use DoleLeilao\Storefront\Application\Port\AuctioneerRepository;
use DoleLeilao\Storefront\Domain\DisplayName;
use DoleLeilao\Storefront\Domain\InvalidDisplayName;
use DoleLeilao\Storefront\Domain\StoreSlug;

/** Sugere o endereço (o primeiro livre) e o nome de exibição a partir do nome da empresa. */
final readonly class SuggestStoreIdentity
{
    private const int MAX_ATTEMPTS = 20;

    public function __construct(private AuctioneerRepository $auctioneers) {}

    public function handle(string $companyName): StoreIdentitySuggestion
    {
        try {
            $displayName = DisplayName::suggestFrom($companyName)->value;
        } catch (InvalidDisplayName) {
            $displayName = null;
        }

        return new StoreIdentitySuggestion($this->firstFreeSlug($companyName), $displayName);
    }

    private function firstFreeSlug(string $companyName): ?string
    {
        $base = StoreSlug::suggestFrom($companyName);
        if ($base === null) {
            return null;
        }

        for ($n = 1; $n <= self::MAX_ATTEMPTS; $n++) {
            $candidate = $n === 1 ? $base : $base->withSuffix($n);
            if (! $this->auctioneers->slugIsTaken($candidate)) {
                return $candidate->value;
            }
        }

        return null;
    }
}
