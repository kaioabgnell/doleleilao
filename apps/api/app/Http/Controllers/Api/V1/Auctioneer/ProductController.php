<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Problem;
use App\Http\Resources\ProductResource;
use DoleLeilao\Catalog\Domain\InvalidProduct;
use DoleLeilao\Catalog\Domain\ProductAttributes;
use DoleLeilao\Catalog\Domain\ProductPricing;
use DoleLeilao\Catalog\Domain\ProductStatus;
use DoleLeilao\Catalog\Domain\YouTubeVideoId;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Shared\Domain\Money;
use DoleLeilao\Shared\Domain\Slugify;
use DoleLeilao\Shared\Domain\ViolationCollector;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use DoleLeilao\Storefront\Infrastructure\Eloquent\CategoryRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Catálogo do leiloeiro (RF-C01 a RF-C06). Tudo filtrado pela loja do token. */
final class ProductController
{
    use ResolvesAuctioneerUser;

    public const int PAGE_SIZE = 24;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:draft,available,in_auction,sold,unsold'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = ProductRecord::query()
            ->with(['photos', 'category'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.addcslashes($request->string('q')->value(), '%_').'%'))
            ->orderByDesc('id')
            ->paginate(self::PAGE_SIZE);

        return response()->json([
            'data' => ProductResource::collection($page->items()),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'counts' => ProductRecord::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(string $product): ProductResource
    {
        return new ProductResource($this->find($product)->load(['photos', 'category']));
    }

    public function store(Request $request): JsonResponse
    {
        $product = new ProductRecord(['status' => ProductStatus::Draft, 'attributes' => []]);
        $this->fill($request, $product, creating: true);
        $this->changed($request, $product);

        return (new ProductResource($product->load(['photos', 'category'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $product): JsonResponse|ProductResource
    {
        $record = $this->find($product);
        if ($record->status->isLocked()) {
            return Problem::make(409, 'Este produto está num leilão ou já foi arrematado e não pode ser alterado.');
        }
        $wasPublic = $record->status->isPublic();
        $this->fill($request, $record, creating: false);
        $this->changed($request, $record, $wasPublic);

        return new ProductResource($record->load(['photos', 'category']));
    }

    public function destroy(Request $request, string $product, MediaStorage $media): JsonResponse
    {
        $record = $this->find($product)->load('photos');
        if ($record->status->isLocked()) {
            return Problem::make(409, 'Este produto está num leilão ou já foi arrematado e não pode ser apagado.');
        }
        foreach ($record->photos as $photo) {
            foreach ($photo->variants ?? [] as $formats) {
                foreach ($formats as $path) {
                    $media->delete($path);
                }
            }
            if ($photo->original_path !== null) {
                Storage::disk('local')->delete($photo->original_path);
            }
        }
        $wasPublic = $record->status->isPublic();
        $record->delete();
        if ($wasPublic) {
            event(new StorefrontChanged($this->currentUser($request)->auctioneer_id));
        }

        return response()->json(null, 204);
    }

    /** Nomes de ficha técnica já usados pela loja (autocompletar, RF-C06). */
    public function attributeNames(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:40']]);
        $q = mb_strtolower($request->string('q')->trim()->value());
        $counts = [];

        foreach (ProductRecord::query()->pluck('attributes') as $attributes) {
            $attributes = is_string($attributes) ? json_decode($attributes, true) : $attributes;
            foreach (is_array($attributes) ? $attributes : [] as $item) {
                $name = is_array($item) && is_string($item['name'] ?? null) ? $item['name'] : null;
                if ($name !== null && ($q === '' || str_contains(mb_strtolower($name), $q))) {
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                }
            }
        }
        arsort($counts);

        return response()->json(array_slice(array_keys($counts), 0, 20));
    }

    private function fill(Request $request, ProductRecord $product, bool $creating): void
    {
        $required = $creating ? 'required' : 'sometimes';
        $request->validate([
            'title' => [$required, 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'string', 'size:26'],
            'reference_price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'starting_price_cents' => [$required, 'integer', 'min:0', 'max:100000000000'],
            'increment_cents' => [$required, 'integer', 'min:0', 'max:10000000000'],
            'youtube_url' => ['nullable', 'string', 'max:255'],
            'attributes' => ['sometimes', 'array', 'max:60'],
            'status' => ['sometimes', 'in:draft,available'],
        ], [], ['title' => 'o título', 'description' => 'a descrição']);

        $errors = new ViolationCollector;

        if ($request->has('title')) {
            $title = $request->string('title')->trim()->value();
            if ($creating || $product->status === ProductStatus::Draft || $title !== $product->title) {
                $product->slug = $creating || $product->status === ProductStatus::Draft ? $this->uniqueSlug($title, $product->id) : $product->slug;
            }
            $product->title = $title;
        }
        if ($request->has('description')) {
            $description = $request->string('description')->trim()->value();
            $product->description = $description === '' ? null : $description;
        }
        if ($request->has('category_id')) {
            $categoryId = $request->string('category_id')->value();
            $category = $categoryId === '' ? null : CategoryRecord::query()->where('public_id', $categoryId)->first();
            if ($categoryId !== '' && $category === null) {
                $errors->add(new InvalidProduct('category_id', 'Categoria não encontrada.'));
            }
            $product->category_id = $category?->id;
        }

        $pricing = $errors->attempt(fn () => new ProductPricing(
            Money::fromCents($request->has('starting_price_cents') ? $request->integer('starting_price_cents') : $product->starting_price),
            Money::fromCents($request->has('increment_cents') ? $request->integer('increment_cents') : $product->increment),
            $request->filled('reference_price_cents') ? Money::fromCents($request->integer('reference_price_cents'))
                : ($request->has('reference_price_cents') || $product->reference_price === null ? null : Money::fromCents($product->reference_price)),
        ));
        if ($pricing !== null) {
            $product->starting_price = $pricing->startingPrice->cents;
            $product->increment = $pricing->increment->cents;
            $product->reference_price = $pricing->referencePrice?->cents;
        }

        if ($request->has('youtube_url')) {
            $url = $request->string('youtube_url')->trim()->value();
            $product->youtube_video_id = $url === '' ? null : $errors->attempt(fn () => YouTubeVideoId::fromUrl($url))?->value;
        }
        if ($request->has('attributes')) {
            /** @var array<mixed> $raw */
            $raw = $request->collect('attributes')->all();
            $attributes = $errors->attempt(fn () => ProductAttributes::fromArray($raw));
            if ($attributes !== null) {
                $product->attributes = $attributes->toArray();
            }
        }

        $status = $request->has('status') ? ProductStatus::from($request->string('status')->value()) : $product->status;
        $photoCount = $product->exists ? $product->photos()->where('status', '!=', 'failed')->count() : 0;
        $errors->attempt(fn () => $status->assertPublishable($photoCount));
        $product->status = $status;

        $errors->throwIfAny();
        $product->save();
    }

    private function uniqueSlug(string $title, ?int $ignoreId): string
    {
        $base = Slugify::from($title, 120);
        $base = $base === '' ? 'produto' : $base;
        $slug = $base;
        for ($n = 2; ProductRecord::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function find(string $publicId): ProductRecord
    {
        return ProductRecord::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function changed(Request $request, ProductRecord $product, bool $wasPublic = false): void
    {
        if ($wasPublic || $product->status->isPublic()) {
            event(new StorefrontChanged($this->currentUser($request)->auctioneer_id));
        }
    }
}
