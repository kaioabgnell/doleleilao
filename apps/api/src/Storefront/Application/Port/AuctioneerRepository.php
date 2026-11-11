<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application\Port;

use DoleLeilao\Shared\Domain\TaxDocument;
use DoleLeilao\Storefront\Application\NewAuctioneer;
use DoleLeilao\Storefront\Application\RegisteredAuctioneer;
use DoleLeilao\Storefront\Domain\Auctioneer;
use DoleLeilao\Storefront\Domain\StoreSlug;

interface AuctioneerRepository
{
    /** Em uso por uma loja ou por um redirecionamento ainda válido de outra loja (D-29). */
    public function slugIsTaken(StoreSlug $slug, ?int $exceptAuctioneerId = null): bool;

    public function documentIsTaken(TaxDocument $document): bool;

    public function emailIsTaken(string $email): bool;

    /**
     * Grava o leiloeiro, o usuário dono e a loja numa transação.
     * Lança as mesmas violações de unicidade se outro cadastro chegar primeiro.
     */
    public function register(NewAuctioneer $data): RegisteredAuctioneer;

    public function get(int $auctioneerId): Auctioneer;

    public function saveSlugChange(Auctioneer $auctioneer, StoreSlug $oldSlug, \DateTimeImmutable $redirectExpiresAt): void;
}
