<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Catalog\Domain\YouTubeVideoId;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Produto. `public: true` esconde os campos internos (loja pública). @mixin ProductRecord */
final class ProductResource extends JsonResource
{
    public bool $public = false;

    public static function forPublic(ProductRecord $product): self
    {
        $resource = new self($product);
        $resource->public = true;

        return $resource;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ProductRecord $p */
        $p = $this->resource;

        $data = [
            'id' => $p->public_id,
            'slug' => $p->slug,
            'title' => $p->title,
            'description' => $p->description,
            'category' => $p->category === null ? null : ['id' => $p->category->public_id, 'name' => $p->category->name, 'slug' => $p->category->slug],
            'reference_price_cents' => $p->reference_price,
            'starting_price_cents' => $p->starting_price,
            'increment_cents' => $p->increment,
            'youtube_video_id' => $p->youtube_video_id,
            'youtube_url' => $p->youtube_video_id === null ? null : YouTubeVideoId::fromId($p->youtube_video_id)->watchUrl(),
            'attributes' => $p->attributes,
            'status' => $p->status->value,
            'views_count' => $p->views_count,
            'photos' => ProductPhotoResource::collection($p->relationLoaded('photos') ? $p->photos : collect()),
        ];

        if (! $this->public) {
            $data['created_at'] = $p->created_at->toIso8601ZuluString();
            $data['updated_at'] = $p->updated_at->toIso8601ZuluString();
        }

        return $data;
    }
}
