<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Problem;
use App\Http\Resources\CategoryResource;
use DoleLeilao\Shared\Domain\Slugify;
use DoleLeilao\Shared\Infrastructure\Media\ImageProcessor;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use DoleLeilao\Storefront\Infrastructure\Eloquent\CategoryRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Categorias da loja (RF-B07). Tudo filtrado pela loja do token (BelongsToTenant). */
final class CategoryController
{
    use ResolvesAuctioneerUser;

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(CategoryRecord::query()->orderBy('position')->orderBy('id')->get());
    }

    public function store(Request $request, ImageProcessor $images, MediaStorage $media): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'image' => ['nullable', 'file', 'max:5120'],
        ], [], ['name' => 'o nome da categoria']);
        if (CategoryRecord::query()->count() >= CategoryRecord::MAX_PER_STORE) {
            return Problem::validation(['name' => ['Sua loja já tem o máximo de '.CategoryRecord::MAX_PER_STORE.' categorias.']]);
        }

        $name = $request->string('name')->trim()->value();
        $category = CategoryRecord::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'position' => $this->nextPosition(),
            'image_path' => $this->storeImage($request, $images, $media),
        ]);
        $this->changed($request);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $category, ImageProcessor $images, MediaStorage $media): CategoryResource
    {
        $record = $this->find($category);
        $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:40'],
            'image' => ['nullable', 'file', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ], [], ['name' => 'o nome da categoria']);

        $name = $request->string('name')->trim()->value();
        if ($request->has('name') && $name !== $record->name) {
            $record->name = $name;
            $record->slug = $this->uniqueSlug($name, $record->id);
        }
        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $media->delete($record->image_path);
            $record->image_path = $this->storeImage($request, $images, $media);
        }
        $record->save();
        $this->changed($request);

        return new CategoryResource($record);
    }

    public function destroy(Request $request, string $category, MediaStorage $media): JsonResponse
    {
        $record = $this->find($category);
        $media->delete($record->image_path);
        $record->delete();
        $this->changed($request);

        return response()->json(null, 204);
    }

    /** PUT /auctioneer/categories/order { ids: [...] } na ordem desejada. */
    public function reorder(Request $request): AnonymousResourceCollection
    {
        $request->validate(['ids' => ['required', 'array', 'max:'.CategoryRecord::MAX_PER_STORE], 'ids.*' => ['string', 'size:26']]);
        $ids = array_values(array_filter($request->collect('ids')->all(), 'is_string'));

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $publicId) {
                CategoryRecord::query()->where('public_id', $publicId)->update(['position' => $position + 1]);
            }
        });
        $this->changed($request);

        return $this->index();
    }

    private function nextPosition(): int
    {
        $max = CategoryRecord::query()->max('position');

        return (is_numeric($max) ? (int) $max : 0) + 1;
    }

    private function find(string $publicId): CategoryRecord
    {
        return CategoryRecord::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Slugify::from($name, 50);
        $base = $base === '' ? 'categoria' : $base;
        $slug = $base;
        for ($n = 2; CategoryRecord::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function storeImage(Request $request, ImageProcessor $images, MediaStorage $media): ?string
    {
        $file = $request->file('image');
        if (! $file instanceof UploadedFile) {
            return null;
        }
        $image = $images->toWebp($file->getRealPath(), 800, 800, 'image');

        return $media->put($this->currentUser($request)->auctioneer->public_id, 'categorias', 'webp', $image->contents);
    }

    private function changed(Request $request): void
    {
        event(new StorefrontChanged($this->currentUser($request)->auctioneer_id));
    }
}
