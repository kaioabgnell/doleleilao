<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

/**
 * Alteração parcial da loja (PATCH). Campos nulos não mudam. Para apagar um contato ou o endereço,
 * o campo aparece em `$cleared`.
 */
final readonly class UpdateStorefrontInput
{
    /**
     * @param  array<string, string>|null  $socials
     * @param  array<string, string|null>|null  $address  postal_code, street, number, complement, district, city, state
     * @param  list<'contact_phone'|'contact_email'|'address'>  $cleared
     */
    public function __construct(
        public int $auctioneerId,
        public ?string $template = null,
        public ?string $primaryColor = null,
        public ?string $accentColor = null,
        public ?string $background = null,
        public ?string $headingFont = null,
        public ?string $contactPhone = null,
        public ?string $contactEmail = null,
        public ?array $socials = null,
        public ?array $address = null,
        public array $cleared = [],
    ) {}
}
