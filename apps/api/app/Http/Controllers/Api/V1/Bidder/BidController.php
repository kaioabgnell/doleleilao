<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bidder;

use App\Http\Resources\AuctionItemResource;
use DoleLeilao\Auction\Application\ItemState;
use DoleLeilao\Auction\Application\ManageAutoBid;
use DoleLeilao\Auction\Application\PlaceBid;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lances do participante (RF-F02, RF-F03). O lance entra por HTTP com chave de idempotência e é
 * distribuído por WebSocket (T-02).
 */
final class BidController
{
    public function store(Request $request, string $item, PlaceBid $placeBid): JsonResponse
    {
        $request->validate(['amount_cents' => ['required', 'integer', 'min:1', 'max:100000000000']], [], ['amount_cents' => 'o valor do lance']);
        $key = (string) $request->header('Idempotency-Key', '');
        if (preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key) !== 1) {
            abort(400, 'Envie o cabeçalho Idempotency-Key.');
        }

        $outcome = $placeBid->execute($this->bidder($request)->id, $item, $request->integer('amount_cents'), $key);

        return response()->json([
            'bid_id' => $outcome->bidPublicId,
            'replayed' => $outcome->replayed,
            'item' => $this->state($outcome->item, $request),
        ], $outcome->replayed ? 200 : 201);
    }

    public function setAutoBid(Request $request, string $item, ManageAutoBid $autoBid): JsonResponse
    {
        $request->validate(['max_amount_cents' => ['required', 'integer', 'min:1', 'max:100000000000']], [], ['max_amount_cents' => 'o valor máximo']);
        $state = $autoBid->set($this->bidder($request)->id, $item, $request->integer('max_amount_cents'));

        return response()->json(['item' => $this->state($state, $request)]);
    }

    public function cancelAutoBid(Request $request, string $item, ManageAutoBid $autoBid): JsonResponse
    {
        return response()->json(['item' => $this->state($autoBid->cancel($this->bidder($request)->id, $item), $request)]);
    }

    /** @return array<string, mixed> */
    private function state(ItemState $s, Request $request): array
    {
        return [
            'id' => $s->publicId,
            'status' => $s->status,
            'current_price_cents' => $s->currentPriceCents,
            'next_minimum_cents' => $s->nextMinimumCents,
            'bids_count' => $s->bidsCount,
            'is_leading' => $s->leadingBidderId === $this->bidder($request)->id,
            'ends_at' => AuctionItemResource::iso($s->endsAt),
            'sequence' => $s->sequence,
            'auto_bid_max_cents' => $s->autoBidMaxCents,
        ];
    }

    private function bidder(Request $request): BidderUser
    {
        /** @var BidderUser */
        return $request->user('sanctum');
    }
}
