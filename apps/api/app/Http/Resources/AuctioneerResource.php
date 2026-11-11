<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Storefront\Domain\SlugChangePolicy;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuctioneerRecord */
final class AuctioneerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AuctioneerRecord $a */
        $a = $this->resource;
        $nextSlugChange = (new SlugChangePolicy)->nextChangeAllowedAt($a->slug_changed_at?->toDateTimeImmutable());

        return [
            'id' => $a->public_id,
            'company_name' => $a->company_name,
            'display_name' => $a->display_name,
            'slug' => $a->slug,
            'store_url' => self::storeUrl($a->slug),
            'document_type' => $a->document_type->value,
            'document_masked' => $a->document()->masked(),
            'status' => $a->status->value,
            'slug_changed_at' => $a->slug_changed_at?->toIso8601ZuluString(),
            'next_slug_change_at' => $nextSlugChange?->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public static function storeUrl(string $slug): string
    {
        return rtrim(config()->string('doleleilao.frontend_url'), '/').'/'.$slug;
    }
}
