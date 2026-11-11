<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Domain\TaxDocument;
use DoleLeilao\Storefront\Domain\DisplayName;
use DoleLeilao\Storefront\Domain\StoreSlug;

/** Dados já validados para gravar um leiloeiro novo. */
final readonly class NewAuctioneer
{
    public function __construct(
        public string $companyName,
        public DisplayName $displayName,
        public StoreSlug $slug,
        public TaxDocument $document,
        public string $ownerName,
        public string $email,
        public Phone $phone,
        public string $passwordHash,
        public \DateTimeImmutable $registeredAt,
    ) {}
}
