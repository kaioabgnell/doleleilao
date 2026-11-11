<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application\Port;

use DoleLeilao\Auction\Domain\Auction;
use DoleLeilao\Auction\Domain\AuctionSettings;
use DoleLeilao\Auction\Domain\ItemSnapshot;

interface AuctionRepository
{
    /** id interno do lote da loja atual, ou null. */
    public function idOf(string $publicId): ?int;

    public function lockForUpdate(int $auctionId): Auction;

    public function save(Auction $auction): void;

    public function settingsOf(int $auctionId): AuctionSettings;

    /** @return list<ItemSnapshot> */
    public function itemSnapshots(int $auctionId): array;

    public function setCurrentItem(int $auctionId, ?int $itemId): void;

    /** Item em pregão (aberto ou pausado) do lote, se houver. */
    public function runningItemId(int $auctionId): ?int;

    public function itemCount(int $auctionId): int;

    /** @return list<int> produtos dos itens ainda na fila */
    public function queuedProductIds(int $auctionId): array;

    /** @return list<int> lotes agendados que já deviam ter começado (todas as lojas, SKIP LOCKED). */
    public function lockDueToStart(\DateTimeImmutable $now, int $limit): array;

    /** @return list<int> lotes ao vivo sem item em pregão (todas as lojas, SKIP LOCKED). */
    public function lockLiveWithoutRunningItem(int $limit): array;
}
