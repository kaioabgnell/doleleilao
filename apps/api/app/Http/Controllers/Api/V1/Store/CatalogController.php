<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Problem;
use App\Http\Resources\ProductResource;
use DoleLeilao\Catalog\Domain\ProductStatus;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Storefront\Infrastructure\Eloquent\CategoryRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Catálogo público da loja (RF-E01, RF-E02, RF-C04). */
final class CatalogController
{
    use ResolvesStore;

    public const int PAGE_SIZE = 24;

    /** Vitrine: produtos disponíveis e em leilão (arrematados aparecem por último). */
    public function index(Request $request, string $slug): JsonResponse
    {
        $this->resolveStore($slug);
        $request->validate([
            'category' => ['nullable', 'string', 'max:60'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $category = null;
        if ($request->filled('category')) {
            $category = CategoryRecord::query()->where('slug', $request->string('category')->value())->first();
            if ($category === null) {
                return Problem::make(404, 'Categoria não encontrada.');
            }
        }

        $page = ProductRecord::query()
            ->with(['photos', 'category'])
            ->whereIn('status', [ProductStatus::Available->value, ProductStatus::InAuction->value, ProductStatus::Sold->value])
            ->when($category, fn ($q) => $q->where('category_id', $category?->id))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.addcslashes($request->string('q')->value(), '%_').'%'))
            ->orderByRaw("FIELD(status, 'in_auction', 'available', 'sold')")
            ->orderByDesc('id')
            ->paginate(self::PAGE_SIZE);

        return response()->json([
            'data' => array_map(fn (ProductRecord $p) => ProductResource::forPublic($p), $page->items()),
            'category' => $category === null ? null : ['id' => $category->public_id, 'name' => $category->name, 'slug' => $category->slug],
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function show(string $slug, string $product): JsonResponse
    {
        $this->resolveStore($slug);
        $record = ProductRecord::query()->with(['photos', 'category'])->where('slug', $product)
            ->where('status', '!=', ProductStatus::Draft->value)->first();

        return $record === null
            ? Problem::make(404, 'Não encontramos este produto.')
            : response()->json(ProductResource::forPublic($record));
    }

    /**
     * Conta 1 visualização por pessoa a cada 30 min (RF-C04). Pessoa = participante logado ou a chave
     * anônima do navegador (cabeçalho X-Viewer-Key). A desduplicação fica no Redis.
     */
    public function view(Request $request, string $slug, string $product): JsonResponse
    {
        $this->resolveStore($slug);
        $record = ProductRecord::query()->where('slug', $product)->where('status', '!=', ProductStatus::Draft->value)->firstOrFail();

        $user = $request->user('sanctum');
        $viewer = $user instanceof BidderUser
            ? 'b'.$user->id
            : 'v'.substr(hash('sha256', (string) $request->header('X-Viewer-Key', (string) $request->ip())), 0, 32);

        if (Cache::add("views:{$record->id}:{$viewer}", 1, now()->addMinutes(30))) {
            DB::transaction(function () use ($record): void {
                ProductRecord::query()->whereKey($record->id)->increment('views_count');
                DB::table('product_view_daily')->upsert(
                    [['product_id' => $record->id, 'date' => now('America/Sao_Paulo')->toDateString(), 'views' => 1]],
                    ['product_id', 'date'],
                    ['views' => DB::raw('views + 1')],
                );
            });
        }

        return response()->json(['views' => ProductRecord::query()->whereKey($record->id)->value('views_count')]);
    }
}
