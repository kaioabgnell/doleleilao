<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\BidRecord;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductPhotoRecord;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Support\Facades\DB;

/** Consultas de leitura do leilão (telas e respostas), separadas das regras. */
final class AuctionReadModel
{
    /**
     * Capa de cada lote: a primeira foto pronta do primeiro item. Fica no atributo `cover_photo`.
     *
     * @param  iterable<AuctionRecord>  $auctions
     */
    public static function attachCovers(iterable $auctions): void
    {
        $byId = [];
        foreach ($auctions as $auction) {
            $byId[$auction->id] = $auction;
        }
        if ($byId === []) {
            return;
        }

        $firstProducts = DB::table('auction_items')
            ->whereIn('auction_id', array_keys($byId))
            ->whereNotNull('product_id')
            ->orderBy('position')
            ->get(['auction_id', 'product_id'])
            ->unique('auction_id')
            ->pluck('product_id', 'auction_id');

        $photos = ProductPhotoRecord::withoutGlobalScope('tenant')
            ->whereIn('product_id', $firstProducts->values())
            ->where('status', 'ready')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        foreach ($firstProducts as $auctionId => $productId) {
            $byId[Scalar::int($auctionId)]->setAttribute('cover_photo', $photos->get(Scalar::int($productId)));
        }
    }

    /**
     * Últimos lances válidos de um item, para a lista do auditório e do painel.
     *
     * @return list<array{id: string, amount_cents: int, type: string, placed_at: string, bidder: array{id: string, nickname: string, name?: string}}>
     */
    public static function recentBids(int $itemId, int $limit = 10, bool $withNames = false): array
    {
        return array_values(BidRecord::query()
            ->with('bidder')
            ->where('auction_item_id', $itemId)
            ->whereNull('voided_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn (BidRecord $b): array => [
                'id' => $b->public_id,
                'amount_cents' => $b->amount,
                'type' => $b->type->value,
                'placed_at' => $b->placed_at->setTimezone('UTC')->format('Y-m-d\\TH:i:s.v\\Z'),
                'bidder' => ['id' => $b->bidder->public_id, 'nickname' => $b->bidder->nickname]
                    + ($withNames ? ['name' => $b->bidder->name] : []),
            ])
            ->all());
    }
}
