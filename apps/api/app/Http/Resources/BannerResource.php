<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Infrastructure\Eloquent\BannerRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BannerRecord */
final class BannerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var BannerRecord $b */
        $b = $this->resource;
        $media = app(MediaStorage::class);

        return [
            'id' => $b->public_id,
            'title' => $b->title,
            'link_url' => $b->link_url,
            'image_mobile_url' => $media->url($b->image_mobile),
            'image_desktop_url' => $media->url($b->image_desktop),
            'position' => $b->position,
            'starts_at' => $b->starts_at?->toIso8601ZuluString(),
            'ends_at' => $b->ends_at?->toIso8601ZuluString(),
            'active' => $b->active,
            'showing_now' => $b->schedule()->showsAt(now()->toDateTimeImmutable(), $b->active),
        ];
    }
}
