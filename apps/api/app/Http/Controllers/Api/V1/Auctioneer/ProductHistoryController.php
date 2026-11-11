<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Problem;
use App\Http\Resources\AuctionItemResource;
use App\Http\Resources\ProductResource;
use DoleLeilao\Auction\Domain\AwardStatus;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionItemRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AwardRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\BidRecord;
use DoleLeilao\Catalog\Domain\ProductStatus;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vida do produto nos leilões: em quais lotes entrou, quem arrematou, lances do item, e a volta
 * para o catálogo ("Retornar item para leilão") quando a venda não se concretiza.
 */
final class ProductHistoryController
{
    public function __construct(private readonly Clock $clock) {}

    public function show(string $product): JsonResponse
    {
        $record = $this->find($product);
        $items = AuctionItemRecord::query()->with('auction')->where('product_id', $record->id)->orderByDesc('id')->get();
        $awards = AwardRecord::query()->with('bidder')->whereIn('auction_item_id', $items->pluck('id'))->get()->keyBy('auction_item_id');
        $bids = BidRecord::query()->with('bidder')->whereIn('auction_item_id', $items->pluck('id'))->orderByDesc('id')->get()->groupBy('auction_item_id');

        return response()->json(['data' => $items->map(function (AuctionItemRecord $item) use ($awards, $bids) {
            $award = $awards->get($item->id);

            return [
                'auction' => [
                    'id' => $item->auction->public_id,
                    'name' => $item->auction->name,
                    'slug' => $item->auction->slug,
                    'status' => $item->auction->status->value,
                    'starts_at' => AuctionItemResource::iso($item->auction->starts_at),
                ],
                'item' => [
                    'id' => $item->public_id,
                    'position' => $item->position,
                    'status' => $item->status->value,
                    'starting_price_cents' => $item->starting_price,
                    'increment_cents' => $item->increment,
                    'current_price_cents' => $item->current_price,
                    'bids_count' => $item->bids_count,
                    'closed_at' => AuctionItemResource::iso($item->closed_at),
                ],
                'award' => $award === null ? null : [
                    'id' => $award->public_id,
                    'amount_cents' => $award->amount,
                    'awarded_at' => AuctionItemResource::iso($award->awarded_at),
                    'status' => $award->status->value,
                    'status_label' => $award->status->label(),
                    'notes' => $award->notes,
                    'bidder' => [
                        'name' => $award->bidder->name,
                        'nickname' => $award->bidder->nickname,
                        'email' => $award->bidder->email,
                        'phone' => Phone::fromString($award->bidder->phone)->formatted(),
                    ],
                ],
                'bids' => ($bids->get($item->id) ?? new EloquentCollection)->map(fn (BidRecord $b) => [
                    'id' => $b->public_id,
                    'amount_cents' => $b->amount,
                    'type' => $b->type->value,
                    'placed_at' => AuctionItemResource::iso($b->placed_at),
                    'voided' => $b->voided_at !== null,
                    'void_reason' => $b->void_reason,
                    'bidder' => ['name' => $b->bidder->name, 'nickname' => $b->bidder->nickname],
                ])->values(),
            ];
        })->values()]);
    }

    /**
     * "Retornar item para leilão": o produto volta a ficar disponível para um novo lote. Se estava
     * arrematado, a arrematação vira desistência, com o motivo nas observações (o histórico fica).
     */
    public function returnToCatalog(Request $request, string $product): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']], [], ['reason' => 'o motivo']);
        $record = $this->find($product);
        if (! in_array($record->status, [ProductStatus::Sold, ProductStatus::Unsold], true)) {
            return Problem::make(409, $record->status === ProductStatus::InAuction
                ? 'O produto está num lote. Tire-o do lote ou espere o leilão terminar.'
                : 'O produto já está no catálogo.');
        }

        DB::transaction(function () use ($record, $request): void {
            if ($record->status === ProductStatus::Sold) {
                $award = AwardRecord::query()
                    ->whereHas('item', fn ($q) => $q->where('product_id', $record->id))
                    ->where('status', '!=', AwardStatus::Withdrawn)
                    ->orderByDesc('awarded_at')
                    ->first();
                if ($award !== null) {
                    $reason = $request->string('reason')->trim()->value();
                    $note = 'Item devolvido para leilão em '.$this->clock->now()->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('d/m/Y').($reason !== '' ? ": {$reason}" : '.');
                    $award->status = AwardStatus::Withdrawn;
                    $award->notes = trim(($award->notes ?? '')."\n".$note);
                    $award->save();
                }
            }
            $record->status = ProductStatus::Available;
            $record->save();
        });
        event(new StorefrontChanged($record->auctioneer_id));

        return response()->json(new ProductResource($record->load(['photos', 'category'])));
    }

    private function find(string $publicId): ProductRecord
    {
        return ProductRecord::query()->where('public_id', $publicId)->firstOrFail();
    }
}
