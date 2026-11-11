<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductPhotoRecord;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lote de leilão. A capa é a enviada pelo leiloeiro (`custom: true`) ou, sem ela, a primeira foto do
 * primeiro item.
 * `public: true` esconde os campos internos (loja pública).
 *
 * @mixin AuctionRecord
 */
final class AuctionResource extends JsonResource
{
    public bool $public = false;

    public static function forPublic(AuctionRecord $auction): self
    {
        $resource = new self($auction);
        $resource->public = true;

        return $resource;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AuctionRecord $a */
        $a = $this->resource;
        $items = $a->relationLoaded('items') ? $a->items : null;

        $data = [
            'id' => $a->public_id,
            'slug' => $a->slug,
            'name' => $a->name,
            'description' => $a->description,
            'status' => $a->status->value,
            'starts_at' => AuctionItemResource::iso($a->starts_at),
            'started_at' => AuctionItemResource::iso($a->started_at),
            'finished_at' => AuctionItemResource::iso($a->finished_at),
            'settings' => $a->settings,
            'items_count' => $items?->count() ?? Scalar::intOrNull($a->getAttribute('items_count')) ?? 0,
            'cover' => $this->cover($a),
            'current_item_id' => $items?->firstWhere('id', $a->current_item_id)?->public_id,
        ];
        if ($items !== null) {
            $data['items'] = AuctionItemResource::collection($items);
        }

        if ($this->public) {
            unset($data['settings']['item_duration_s'], $data['settings']['gap_s']);
        } else {
            $data['participants_count'] = Scalar::intOrNull($a->getAttribute('participations_count')) ?? 0;
            $data['visible_in_store'] = $a->visible_in_store;
            $data['created_at'] = $a->created_at->toIso8601ZuluString();
        }

        return $data;
    }

    /** @return array{thumb: string|null, card: array{webp: string|null, avif: string|null}, custom: bool}|null */
    private function cover(AuctionRecord $a): ?array
    {
        $media = app(MediaStorage::class);
        if ($a->cover_path !== null) {
            return [
                'thumb' => $media->url($a->cover_thumb_path ?? $a->cover_path),
                'card' => ['webp' => $media->url($a->cover_path), 'avif' => null],
                'custom' => true,
            ];
        }

        $photo = $a->getAttribute('cover_photo');
        if (! $photo instanceof ProductPhotoRecord) {
            return null;
        }

        return [
            'thumb' => $media->url($photo->variants['thumb']['webp'] ?? null),
            'card' => ['webp' => $media->url($photo->variants['card']['webp'] ?? null), 'avif' => $media->url($photo->variants['card']['avif'] ?? null)],
            'custom' => false,
        ];
    }
}
