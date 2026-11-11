<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application\Port;

use DoleLeilao\Auction\Application\StoredBid;
use DoleLeilao\Auction\Domain\AuctionItem;

interface AuctionItemRepository
{
    /** id interno do item da loja atual, ou null. */
    public function idOf(string $publicId): ?int;

    /** Carrega com `SELECT … FOR UPDATE` (precisa estar numa transação): um escritor por item. */
    public function lockForUpdate(int $itemId): AuctionItem;

    /**
     * Itens abertos com o tempo esgotado, travados com SKIP LOCKED (outro relógio pula os mesmos).
     * Vale para todas as lojas.
     *
     * @return list<AuctionItem>
     */
    public function lockExpired(\DateTimeImmutable $now, int $limit): array;

    /** Grava o item, os lances novos e os automáticos. */
    public function save(AuctionItem $item): void;

    public function findByIdempotencyKey(int $bidderId, string $key): ?StoredBid;

    /** @return list<StoredBid> os últimos lances válidos, do mais novo para o mais antigo. */
    public function lastValidBids(int $itemId, int $limit): array;

    public function productIdOf(int $itemId): ?int;

    public function markVoided(int $bidId, string $reason, ?int $voidedBy, \DateTimeImmutable $at): void;
}
