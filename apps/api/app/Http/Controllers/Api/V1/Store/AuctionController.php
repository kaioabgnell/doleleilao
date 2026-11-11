<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Resources\AuctionItemResource;
use App\Http\Resources\AuctionResource;
use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Auction\Infrastructure\AuctionReadModel;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionItemRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Shared\Domain\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Leilões na loja pública (RF-E05) e o estado do auditório (RF-F01, RF-F11). */
final class AuctionController
{
    use ResolvesStore;

    public function __construct(private readonly Clock $clock) {}

    /** `status`: upcoming (agendados e ao vivo) ou finished. */
    public function index(Request $request, string $slug): JsonResponse
    {
        $this->resolveStore($slug);
        $request->validate(['status' => ['nullable', 'in:upcoming,finished']]);
        $finished = $request->string('status')->value() === 'finished';

        $auctions = AuctionRecord::query()
            ->withCount('items')
            ->whereIn('status', $finished ? [AuctionStatus::Finished] : [AuctionStatus::Live, AuctionStatus::Scheduled])
            ->where('visible_in_store', true)
            ->when($finished, fn ($q) => $q->orderByDesc('starts_at'), fn ($q) => $q->orderByRaw("status = 'live' desc")->orderBy('starts_at'))
            ->limit(50)
            ->get();
        AuctionReadModel::attachCovers($auctions);

        return response()->json(['data' => $auctions->map(fn (AuctionRecord $a) => AuctionResource::forPublic($a))]);
    }

    /** Página do leilão / auditório: lote, itens (com produto) e o estado atual. */
    public function show(string $slug, string $auction): JsonResponse
    {
        $this->resolveStore($slug);
        $record = $this->find($auction)->load(['items.product.photos', 'items.product.category', 'items.leader']);
        AuctionReadModel::attachCovers([$record]);

        return response()->json([
            'auction' => AuctionResource::forPublic($record),
            'recent_bids' => $this->recentBids($record),
            'server_now' => AuctionItemResource::iso($this->clock->now()),
        ]);
    }

    /** Estado leve para reconstruir o auditório depois de reconectar (sem os dados do produto). */
    public function state(string $slug, string $auction): JsonResponse
    {
        $this->resolveStore($slug);
        $record = $this->find($auction)->load('items.leader');
        $items = $record->items->map(function (AuctionItemRecord $i) {
            $resource = new AuctionItemResource($i);
            $resource->withProduct = false;

            return $resource;
        });

        return response()->json([
            'status' => $record->status->value,
            'current_item_id' => $record->items->firstWhere('id', $record->current_item_id)?->public_id,
            'items' => $items,
            'recent_bids' => $this->recentBids($record),
            'server_now' => AuctionItemResource::iso($this->clock->now()),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function recentBids(AuctionRecord $record): array
    {
        $running = $record->items->first(fn (AuctionItemRecord $i): bool => $i->status->isRunning());

        return $running === null ? [] : AuctionReadModel::recentBids($running->id);
    }

    /** Pelo endereço do leilão ou pelo id público. Rascunhos, cancelados e escondidos não aparecem. */
    private function find(string $auction): AuctionRecord
    {
        return AuctionRecord::query()
            ->where(fn ($q) => $q->where('slug', $auction)->orWhere('public_id', $auction))
            ->whereNotIn('status', [AuctionStatus::Draft, AuctionStatus::Cancelled])
            ->where('visible_in_store', true)
            ->firstOrFail();
    }
}
