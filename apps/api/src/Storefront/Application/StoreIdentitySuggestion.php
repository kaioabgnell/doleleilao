<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

final readonly class StoreIdentitySuggestion
{
    public function __construct(
        public ?string $slug,
        public ?string $displayName,
    ) {}
}
