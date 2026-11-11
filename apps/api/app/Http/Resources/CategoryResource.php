<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Infrastructure\Eloquent\CategoryRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CategoryRecord */
final class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CategoryRecord $c */
        $c = $this->resource;

        return [
            'id' => $c->public_id,
            'name' => $c->name,
            'slug' => $c->slug,
            'position' => $c->position,
            'image_url' => app(MediaStorage::class)->url($c->image_path),
        ];
    }
}
