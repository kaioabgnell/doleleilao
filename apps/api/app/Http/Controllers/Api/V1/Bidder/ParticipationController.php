<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bidder;

use App\Http\Problem;
use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Auction\Domain\Event\ParticipationConfirmed;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AutoBidRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\ParticipationRecord;
use DoleLeilao\Auction\Infrastructure\EloquentBidderGate;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Quero participar" (RF-E10, D-44) e a situação do participante num leilão: habilitado
 * (cadastro completo, D-23), o que falta, lances automáticos cadastrados.
 */
final class ParticipationController
{
    public function show(Request $request, string $auction): JsonResponse
    {
        $bidder = $this->bidder($request);
        $record = $this->find($auction);

        return response()->json($this->situation($bidder, $record));
    }

    public function store(Request $request, string $auction): JsonResponse
    {
        $bidder = $this->bidder($request);
        $record = $this->find($auction);
        if (! in_array($record->status, [AuctionStatus::Scheduled, AuctionStatus::Live], true)) {
            return Problem::make(409, 'Este leilão já terminou.');
        }

        $participation = ParticipationRecord::query()->firstOrCreate(['auction_id' => $record->id, 'bidder_id' => $bidder->id]);
        if ($participation->wasRecentlyCreated) {
            event(new ParticipationConfirmed($record->id, $record->auctioneer_id, $bidder->id, $participation->public_id));
        }

        return response()->json($this->situation($bidder, $record), $participation->wasRecentlyCreated ? 201 : 200);
    }

    /** Dá para desmarcar até o leilão começar. */
    public function destroy(Request $request, string $auction): JsonResponse
    {
        $bidder = $this->bidder($request);
        $record = $this->find($auction);
        if ($record->status !== AuctionStatus::Scheduled) {
            return Problem::make(409, 'O leilão já começou.');
        }
        ParticipationRecord::query()->where('auction_id', $record->id)->where('bidder_id', $bidder->id)->delete();

        return response()->json($this->situation($bidder, $record));
    }

    /** @return array<string, mixed> */
    private function situation(BidderUser $bidder, AuctionRecord $auction): array
    {
        $autoBids = AutoBidRecord::query()
            ->join('auction_items', 'auction_items.id', '=', 'auto_bids.auction_item_id')
            ->where('auction_items.auction_id', $auction->id)
            ->where('auto_bids.bidder_id', $bidder->id)
            ->where('auto_bids.active', true)
            ->pluck('auto_bids.max_amount', 'auction_items.public_id')
            ->map(fn (mixed $v): int => Scalar::int($v));

        return [
            'bidder_id' => $bidder->public_id,
            'nickname' => $bidder->nickname,
            'participating' => ParticipationRecord::query()->where('auction_id', $auction->id)->where('bidder_id', $bidder->id)->exists(),
            'eligible' => $bidder->isEligible(),
            'missing' => $bidder->missingForEligibility(),
            'banned' => EloquentBidderGate::isBanned($bidder->auctioneer_id, $bidder->id),
            'auto_bids' => (object) $autoBids->all(),
        ];
    }

    private function find(string $auction): AuctionRecord
    {
        return AuctionRecord::query()
            ->where(fn ($q) => $q->where('slug', $auction)->orWhere('public_id', $auction))
            ->whereNotIn('status', [AuctionStatus::Draft, AuctionStatus::Cancelled])
            ->firstOrFail();
    }

    private function bidder(Request $request): BidderUser
    {
        /** @var BidderUser */
        return $request->user('sanctum');
    }
}
