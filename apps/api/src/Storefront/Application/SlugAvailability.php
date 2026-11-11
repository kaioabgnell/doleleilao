<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

final readonly class SlugAvailability
{
    public function __construct(
        public string $slug,
        public bool $available,
        /** invalid | reserved | taken */
        public ?string $reason = null,
        public ?string $message = null,
    ) {}
}
