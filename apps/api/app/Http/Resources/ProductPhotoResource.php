<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductPhotoRecord;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductPhotoRecord */
final class ProductPhotoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ProductPhotoRecord $p */
        $p = $this->resource;
        $media = app(MediaStorage::class);
        $url = fn (string $size, string $format): ?string => $media->url($p->variants[$size][$format] ?? null);

        return [
            'id' => $p->public_id,
            'status' => $p->status,
            'position' => $p->position,
            'width' => $p->width,
            'height' => $p->height,
            'thumb' => $url('thumb', 'webp'),
            'card' => ['webp' => $url('card', 'webp'), 'avif' => $url('card', 'avif')],
            'zoom' => ['webp' => $url('zoom', 'webp'), 'avif' => $url('zoom', 'avif')],
        ];
    }
}
