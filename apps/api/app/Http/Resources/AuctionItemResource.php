<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionItemRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item de um lote com o estado do pregão. O cronômetro usa `ends_at` (UTC, milissegundos) e o
 * `server_now` da resposta. Quem está ganhando aparece só pelo apelido (T-14).
 *
 * @mixin AuctionItemRecord
 */
final class AuctionItemResource extends JsonResource
{
    public bool $withProduct = true;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AuctionItemRecord $i */
        $i = $this->resource;
        $next = $i->current_price === null ? $i->starting_price : $i->current_price + $i->increment;

        return [
            'id' => $i->public_id,
            'position' => $i->position,
            'title' => $i->title,
            'status' => $i->status->value,
            'starting_price_cents' => $i->starting_price,
            'increment_cents' => $i->increment,
            'current_price_cents' => $i->current_price,
            'next_minimum_cents' => $next,
            'bids_count' => $i->bids_count,
            'leader' => $i->leading_bidder_id === null || ! $i->relationLoaded('leader') || $i->leader === null
                ? null
                : ['id' => $i->leader->public_id, 'nickname' => $i->leader->nickname],
            'opened_at' => self::iso($i->opened_at),
            'ends_at' => self::iso($i->ends_at),
            'paused_remaining_ms' => $i->paused_remaining_ms,
            'closed_at' => self::iso($i->closed_at),
            'sequence' => $i->sequence,
            'product' => $this->withProduct && $i->relationLoaded('product') && $i->product !== null
                ? ProductResource::forPublic($i->product)
                : null,
        ];
    }

    public static function iso(?\DateTimeInterface $at): ?string
    {
        return $at === null ? null : \DateTimeImmutable::createFromInterface($at)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }
}
