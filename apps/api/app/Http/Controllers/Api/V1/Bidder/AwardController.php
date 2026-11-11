<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bidder;

use App\Http\Controllers\Api\V1\Auctioneer\AwardController as PanelAwards;
use App\Http\Resources\AuctionItemResource;
use App\Http\Resources\AuctionResource;
use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Auction\Infrastructure\AuctionReadModel;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AwardRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\WinnerMessageRecord;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductPhotoRecord;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** "Minha conta" do participante (RF-E07, RF-E08): arrematações com as mensagens da loja e próximos leilões. */
final class AwardController
{
    public function awards(Request $request): JsonResponse
    {
        $awards = AwardRecord::query()
            ->with(['item.auction', 'item.product.photos', 'messages'])
            ->where('bidder_id', $this->bidder($request)->id)
            ->orderByDesc('awarded_at')
            ->get();

        return response()->json(['data' => $awards->map(function (AwardRecord $a) {
            $photo = $a->item->product?->photos->first(fn (ProductPhotoRecord $p) => $p->status === 'ready');

            return [
                'id' => $a->public_id,
                'amount_cents' => $a->amount,
                'awarded_at' => AuctionItemResource::iso($a->awarded_at),
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'item' => [
                    'title' => $a->item->title,
                    'thumb' => $photo === null ? null : app(MediaStorage::class)->url($photo->variants['thumb']['webp'] ?? null),
                    'product_slug' => $a->item->product?->slug,
                ],
                'auction' => ['name' => $a->item->auction->name, 'slug' => $a->item->auction->slug],
                'messages' => $a->messages->map(fn (WinnerMessageRecord $m) => PanelAwards::message_($m))->values(),
            ];
        })->values()]);
    }

    /** Próximos leilões: os que a pessoa marcou "Quero participar" e os que têm itens favoritos dela. */
    public function upcoming(Request $request): JsonResponse
    {
        $bidder = $this->bidder($request);
        $participating = DB::table('auction_participations')->where('bidder_id', $bidder->id)->pluck('auction_id');
        $withFavorites = DB::table('favorites')
            ->join('auction_items', 'auction_items.product_id', '=', 'favorites.product_id')
            ->where('favorites.bidder_id', $bidder->id)
            ->pluck('auction_items.auction_id');

        $auctions = AuctionRecord::query()
            ->withCount('items')
            ->whereIn('id', $participating->merge($withFavorites)->unique()->values())
            ->whereIn('status', [AuctionStatus::Scheduled, AuctionStatus::Live])
            ->orderByRaw("status = 'live' desc")->orderBy('starts_at')
            ->get();
        AuctionReadModel::attachCovers($auctions);

        return response()->json(['data' => $auctions->map(fn (AuctionRecord $a) => [
            'auction' => AuctionResource::forPublic($a),
            'participating' => $participating->contains($a->id),
            'has_favorites' => $withFavorites->contains($a->id),
        ])->values()]);
    }

    private function bidder(Request $request): BidderUser
    {
        /** @var BidderUser */
        return $request->user('sanctum');
    }
}
