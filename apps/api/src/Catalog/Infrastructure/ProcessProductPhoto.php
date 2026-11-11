<?php

declare(strict_types=1);

namespace DoleLeilao\Catalog\Infrastructure;

use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductPhotoRecord;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Shared\Infrastructure\Media\ImageProcessor;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gera os tamanhos da foto do produto na fila (RF-C02): miniatura 320 px, card 640 px e zoom 1600 px,
 * em WebP (e AVIF no card e no zoom). Apaga o original enviado.
 */
final class ProcessProductPhoto implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public const array SIZES = [
        'thumb' => ['width' => 320, 'avif' => false],
        'card' => ['width' => 640, 'avif' => true],
        'zoom' => ['width' => 1600, 'avif' => true],
    ];

    public function __construct(public readonly int $photoId, public readonly int $auctioneerId) {}

    public function handle(ImageProcessor $images, CurrentTenant $tenant): void
    {
        $tenant->runAs($this->auctioneerId, function () use ($images): void {
            $photo = ProductPhotoRecord::query()->find($this->photoId);
            $original = $photo?->original_path;
            if ($photo === null || $original === null) {
                return;
            }
            $auctioneer = AuctioneerRecord::query()->findOrFail($this->auctioneerId);
            $local = Storage::disk('local');

            try {
                $variants = $images->variants($local->path($original), self::SIZES, 'photos');
            } catch (\Throwable) {
                $photo->forceFill(['status' => 'failed'])->save();
                $local->delete($original);

                return;
            }

            $base = sprintf('lojas/%s/produtos/%s', $auctioneer->public_id, strtolower((string) Str::ulid()));
            $paths = [];
            foreach ($variants as $size => $variant) {
                foreach (['webp', 'avif'] as $format) {
                    if ($variant[$format] !== null) {
                        $path = "{$base}-{$size}.{$format}";
                        Storage::disk('public')->put($path, $variant[$format], ['visibility' => 'public']);
                        $paths[$size][$format] = $path;
                    }
                }
            }

            $local->delete($original);
            $photo->forceFill([
                'variants' => $paths,
                'status' => 'ready',
                'original_path' => null,
                'width' => $variants['zoom']['width'],
                'height' => $variants['zoom']['height'],
            ])->save();

            if (ProductRecord::query()->whereKey($photo->product_id)->where('status', '!=', 'draft')->exists()) {
                event(new StorefrontChanged($this->auctioneerId));
            }
        });
    }
}
