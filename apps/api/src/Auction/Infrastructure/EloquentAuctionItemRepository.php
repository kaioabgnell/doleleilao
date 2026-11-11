<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Application\ItemNotFound;
use DoleLeilao\Auction\Application\Port\AuctionItemRepository;
use DoleLeilao\Auction\Application\StoredBid;
use DoleLeilao\Auction\Domain\AuctionItem;
use DoleLeilao\Auction\Domain\AutoBid;
use DoleLeilao\Auction\Domain\PlacedBid;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionItemRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AutoBidRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\BidRecord;
use DoleLeilao\Shared\Domain\Money;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Support\Facades\DB;

/**
 * Itens do lote. As leituras por id ignoram o escopo da loja: o id vem de `idOf` (que respeita a loja)
 * ou do relógio, que roda para todas as lojas. Os registros carregados ficam guardados para o `save`.
 */
final class EloquentAuctionItemRepository implements AuctionItemRepository
{
    /** @var array<int, AuctionItemRecord> */
    private array $loaded = [];

    public function idOf(string $publicId): ?int
    {
        $id = AuctionItemRecord::query()->where('public_id', $publicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function lockForUpdate(int $itemId): AuctionItem
    {
        $record = AuctionItemRecord::withoutGlobalScope('tenant')->lockForUpdate()->find($itemId) ?? throw new ItemNotFound;
        $this->loaded[$record->id] = $record;

        $autos = [];
        foreach (AutoBidRecord::withoutGlobalScope('tenant')->where('auction_item_id', $record->id)->where('active', true)->get() as $auto) {
            $autos[$auto->bidder_id] = new AutoBid($auto->bidder_id, Money::fromCents($auto->max_amount), $auto->registered_at->toDateTimeImmutable());
        }

        return new AuctionItem(
            id: $record->id,
            publicId: $record->public_id,
            auctionId: $record->auction_id,
            position: $record->position,
            status: $record->status,
            startingPrice: Money::fromCents($record->starting_price),
            increment: Money::fromCents($record->increment),
            currentPrice: $record->current_price === null ? null : Money::fromCents($record->current_price),
            leadingBidderId: $record->leading_bidder_id,
            bidsCount: $record->bids_count,
            openedAt: $record->opened_at?->toDateTimeImmutable(),
            endsAt: $record->ends_at?->toDateTimeImmutable(),
            pausedRemainingMs: $record->paused_remaining_ms,
            closedAt: $record->closed_at?->toDateTimeImmutable(),
            sequence: $record->sequence,
            autoBids: $autos,
        );
    }

    public function lockExpired(\DateTimeImmutable $now, int $limit): array
    {
        $ids = DB::table('auction_items')
            ->where('status', 'open')
            ->where('ends_at', '<=', $now->format('Y-m-d H:i:s.u'))
            ->orderBy('ends_at')
            ->limit($limit)
            ->lock('for update skip locked')
            ->pluck('id');

        return array_map(fn (int $id): AuctionItem => $this->lockForUpdate($id), Scalar::ints($ids));
    }

    public function save(AuctionItem $item): void
    {
        $record = $this->loaded[$item->id] ?? throw new \LogicException('Item não foi carregado com trava.');
        unset($this->loaded[$item->id]);

        $record->fill([
            'status' => $item->status(),
            'starting_price' => $item->startingPrice()->cents,
            'increment' => $item->increment()->cents,
            'current_price' => $item->currentPrice()?->cents,
            'leading_bidder_id' => $item->leadingBidderId(),
            'bids_count' => $item->bidsCount(),
            'opened_at' => $item->openedAt(),
            'ends_at' => $item->endsAt(),
            'paused_remaining_ms' => $item->pausedRemainingMs(),
            'closed_at' => $item->closedAt(),
            'sequence' => $item->sequence(),
        ])->save();

        foreach ($item->releaseNewBids() as $bid) {
            BidRecord::withoutGlobalScope('tenant')->create([
                'auctioneer_id' => $record->auctioneer_id,
                'auction_item_id' => $record->id,
                'bidder_id' => $bid->bidderId,
                'amount' => $bid->amount->cents,
                'type' => $bid->type,
                'placed_at' => $bid->placedAt,
                'idempotency_key' => $bid->idempotencyKey,
                'created_at' => $bid->placedAt,
            ]);
        }

        foreach ($item->autoBids() as $auto) {
            AutoBidRecord::withoutGlobalScope('tenant')->updateOrCreate(
                ['auction_item_id' => $record->id, 'bidder_id' => $auto->bidderId],
                [
                    'auctioneer_id' => $record->auctioneer_id,
                    'max_amount' => $auto->maxAmount->cents,
                    'registered_at' => $auto->registeredAt,
                    'active' => $auto->active,
                ],
            );
        }
    }

    public function findByIdempotencyKey(int $bidderId, string $key): ?StoredBid
    {
        $record = BidRecord::withoutGlobalScope('tenant')->where('bidder_id', $bidderId)->where('idempotency_key', $key)->first();

        return $record === null ? null : $this->stored($record);
    }

    public function lastValidBids(int $itemId, int $limit): array
    {
        return array_values(BidRecord::withoutGlobalScope('tenant')
            ->where('auction_item_id', $itemId)
            ->whereNull('voided_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (BidRecord $r): StoredBid => $this->stored($r))
            ->all());
    }

    public function productIdOf(int $itemId): ?int
    {
        $id = DB::table('auction_items')->where('id', $itemId)->value('product_id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function markVoided(int $bidId, string $reason, ?int $voidedBy, \DateTimeImmutable $at): void
    {
        BidRecord::withoutGlobalScope('tenant')->whereKey($bidId)->update([
            'voided_at' => $at->format('Y-m-d H:i:s.u'),
            'void_reason' => $reason,
            'voided_by' => $voidedBy,
        ]);
    }

    private function stored(BidRecord $r): StoredBid
    {
        return new StoredBid($r->id, $r->public_id, new PlacedBid(
            $r->bidder_id,
            Money::fromCents($r->amount),
            $r->type,
            $r->placed_at->toDateTimeImmutable(),
            $r->idempotency_key,
        ));
    }
}
