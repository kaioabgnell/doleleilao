<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bidder;

use App\Http\Resources\ProductResource;
use DoleLeilao\Catalog\Domain\ProductStatus;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Favoritos do participante (RF-E05). Só produtos públicos da própria loja. */
final class FavoriteController
{
    public function index(Request $request): JsonResponse
    {
        $ids = DB::table('favorites')->where('bidder_id', $this->bidderId($request))->orderByDesc('created_at')->pluck('product_id');
        $products = ProductRecord::query()->with(['photos', 'category'])->whereIn('id', $ids)
            ->where('status', '!=', ProductStatus::Draft->value)->get()
            ->sortBy(fn (ProductRecord $p) => $ids->search($p->id))->values();

        return response()->json([
            'data' => $products->map(fn (ProductRecord $p) => ProductResource::forPublic($p)),
            'ids' => $products->pluck('public_id'),
        ]);
    }

    public function store(Request $request, string $product): JsonResponse
    {
        $record = $this->publicProduct($product);
        DB::table('favorites')->insertOrIgnore(['bidder_id' => $this->bidderId($request), 'product_id' => $record->id, 'created_at' => now()]);

        return response()->json(['favorite' => true]);
    }

    public function destroy(Request $request, string $product): JsonResponse
    {
        $record = $this->publicProduct($product);
        DB::table('favorites')->where('bidder_id', $this->bidderId($request))->where('product_id', $record->id)->delete();

        return response()->json(['favorite' => false]);
    }

    private function publicProduct(string $publicId): ProductRecord
    {
        return ProductRecord::query()->where('public_id', $publicId)->where('status', '!=', ProductStatus::Draft->value)->firstOrFail();
    }

    private function bidderId(Request $request): int
    {
        $user = $request->user('sanctum');

        return $user instanceof BidderUser ? $user->id : abort(401);
    }
}
