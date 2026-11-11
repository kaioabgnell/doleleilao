<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

final readonly class RegisterAuctioneerInput
{
    public function __construct(
        public string $ownerName,
        public string $email,
        public string $phone,
        public string $password,
        public string $documentType,
        public string $document,
        public string $companyName,
        public string $slug,
        public ?string $displayName = null,
    ) {}
}
