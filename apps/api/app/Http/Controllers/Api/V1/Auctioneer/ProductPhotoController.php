<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Problem;
use App\Http\Resources\ProductPhotoResource;
use DoleLeilao\Catalog\Domain\ProductStatus;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductPhotoRecord;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Catalog\Infrastructure\ProcessProductPhoto;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Fotos do produto (RF-C02): até 10, 10 MB cada. O processamento roda na fila. */
final class ProductPhotoController
{
    use ResolvesAuctioneerUser;

    private const array ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

    public function store(Request $request, string $product): JsonResponse
    {
        $record = ProductRecord::query()->where('public_id', $product)->firstOrFail();
        $request->validate(['photos' => ['required', 'array', 'min:1', 'max:'.ProductPhotoRecord::MAX_PER_PRODUCT], 'photos.*' => ['file', 'max:10240']], [], ['photos' => 'as fotos']);

        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) $request->file('photos'), fn ($f) => $f instanceof UploadedFile));
        $existing = $record->photos()->count();
        if ($existing + count($files) > ProductPhotoRecord::MAX_PER_PRODUCT) {
            return Problem::validation(['photos' => ['Cada produto pode ter até '.ProductPhotoRecord::MAX_PER_PRODUCT.' fotos. Restam '.max(0, ProductPhotoRecord::MAX_PER_PRODUCT - $existing).'.']]);
        }
        foreach ($files as $file) {
            if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()), self::ACCEPTED, true)) {
                return Problem::validation(['photos' => ["“{$file->getClientOriginalName()}” não é uma imagem JPG, PNG ou WebP."]]);
            }
        }

        $auctioneerId = $this->currentUser($request)->auctioneer_id;
        $created = [];
        foreach ($files as $i => $file) {
            $path = $file->storeAs('uploads/produtos', strtolower((string) Str::ulid()).'.bin', 'local');
            $photo = ProductPhotoRecord::query()->create([
                'product_id' => $record->id,
                'original_path' => $path,
                'position' => $existing + $i + 1,
            ]);
            ProcessProductPhoto::dispatch($photo->id, $auctioneerId);
            $created[] = $photo->refresh();
        }

        return ProductPhotoResource::collection($created)->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $product, string $photo, MediaStorage $media): JsonResponse
    {
        $record = ProductRecord::query()->where('public_id', $product)->firstOrFail();
        $item = $record->photos()->where('public_id', $photo)->firstOrFail();

        if ($record->status === ProductStatus::Available && $record->photos()->where('status', '!=', 'failed')->count() <= 1) {
            return Problem::validation(['photos' => ['Produto disponível precisa de pelo menos 1 foto. Adicione outra antes de apagar esta, ou volte o produto para rascunho.']]);
        }

        foreach ($item->variants ?? [] as $formats) {
            foreach ($formats as $path) {
                $media->delete($path);
            }
        }
        $item->delete();
        if ($record->status->isPublic()) {
            event(new StorefrontChanged($this->currentUser($request)->auctioneer_id));
        }

        return response()->json(null, 204);
    }

    public function reorder(Request $request, string $product): AnonymousResourceCollection
    {
        $record = ProductRecord::query()->where('public_id', $product)->firstOrFail();
        $request->validate(['ids' => ['required', 'array', 'max:'.ProductPhotoRecord::MAX_PER_PRODUCT], 'ids.*' => ['string', 'size:26']]);
        $ids = array_values(array_filter($request->collect('ids')->all(), 'is_string'));

        DB::transaction(function () use ($ids, $record): void {
            foreach ($ids as $position => $publicId) {
                $record->photos()->where('public_id', $publicId)->update(['position' => $position + 1]);
            }
        });
        if ($record->status->isPublic()) {
            event(new StorefrontChanged($this->currentUser($request)->auctioneer_id));
        }

        return ProductPhotoResource::collection($record->photos()->get());
    }
}
