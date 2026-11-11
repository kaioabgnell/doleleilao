<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Domain\Event\AuctionFinished;
use DoleLeilao\Auction\Domain\Event\AuctionStarted;
use DoleLeilao\Auction\Domain\Event\AutoBidExhausted;
use DoleLeilao\Auction\Domain\Event\BidAccepted;
use DoleLeilao\Auction\Domain\Event\BidderOutbid;
use DoleLeilao\Auction\Domain\Event\BidVoided;
use DoleLeilao\Auction\Domain\Event\ItemAwarded;
use DoleLeilao\Auction\Domain\Event\ItemClosed;
use DoleLeilao\Auction\Domain\Event\ItemOpened;
use DoleLeilao\Auction\Domain\Event\ItemPaused;
use DoleLeilao\Auction\Domain\Event\ItemResumed;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

/**
 * Distribui os eventos do pregão pelo Reverb (specs/06-api-e-tempo-real.md#eventos), logo depois do
 * commit e sem fila (meta de 300 ms, RF-F02). Os nomes e os campos estão em `packages/realtime-contract`.
 * Falha do WebSocket nunca desfaz um lance: o erro é registrado e o relógio pausa o pregão (regra 9).
 */
final class BroadcastAuctionEvents
{
    public function handle(object $event): void
    {
        try {
            $this->dispatch($event);
        } catch (\Throwable $e) {
            RealtimeHealth::recordFailure();
            report($e);
        }
    }

    private function dispatch(object $event): void
    {
        $now = self::iso(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        match (true) {
            $event instanceof ItemOpened => $this->auction($event->auctionId, 'item.opened', [
                'item_id' => $event->itemPublicId,
                'starting_price_cents' => $event->startingPrice->cents,
                'increment_cents' => $event->increment->cents,
                'next_minimum_cents' => $event->startingPrice->cents,
                'ends_at' => self::iso($event->endsAt),
                'sequence' => $event->sequence,
                'server_now' => $now,
            ]),
            $event instanceof BidAccepted => $this->auction($event->auctionId, 'bid.accepted', [
                'item_id' => $event->itemPublicId,
                'amount_cents' => $event->bid->amount->cents,
                'bidder' => $this->bidder($event->bid->bidderId),
                'type' => $event->bid->type->value,
                'placed_at' => self::iso($event->bid->placedAt),
                'next_minimum_cents' => $event->nextMinimum->cents,
                'bids_count' => $event->bidsCount,
                'ends_at' => self::iso($event->endsAt),
                'extended' => $event->extended,
                'sequence' => $event->sequence,
                'server_now' => $now,
            ]),
            $event instanceof ItemPaused => $this->auction($event->auctionId, 'item.paused', [
                'item_id' => $event->itemPublicId,
                'remaining_ms' => $event->remainingMs,
                'sequence' => $event->sequence,
                'server_now' => $now,
            ]),
            $event instanceof ItemResumed => $this->auction($event->auctionId, 'item.resumed', [
                'item_id' => $event->itemPublicId,
                'ends_at' => self::iso($event->endsAt),
                'sequence' => $event->sequence,
                'server_now' => $now,
            ]),
            $event instanceof ItemClosed => $this->auction($event->auctionId, 'item.closed', [
                'item_id' => $event->itemPublicId,
                'result' => $event->result->value,
                'amount_cents' => $event->amount?->cents,
                'winner' => $event->winnerId === null ? null : $this->bidder($event->winnerId),
                'sequence' => $event->sequence,
                'server_now' => $now,
            ]),
            $event instanceof BidVoided => $this->auction($event->auctionId, 'bid.voided', [
                'item_id' => $event->itemPublicId,
                'current_price_cents' => $event->currentPrice?->cents,
                'leader' => $event->leadingBidderId === null ? null : $this->bidder($event->leadingBidderId),
                'next_minimum_cents' => $event->nextMinimum->cents,
                'bids_count' => $event->bidsCount,
                'sequence' => $event->sequence,
                'server_now' => $now,
            ]),
            $event instanceof AuctionStarted => $this->auction($event->auctionId, 'auction.started', ['server_now' => $now]),
            $event instanceof AuctionFinished => $this->auction($event->auctionId, 'auction.finished', ['server_now' => $now]),
            $event instanceof BidderOutbid => $this->toBidder($event->bidderId, 'bidder.outbid', [
                'item_id' => $event->itemPublicId,
                'new_price_cents' => $event->newPrice->cents,
            ]),
            $event instanceof AutoBidExhausted => $this->toBidder($event->bidderId, 'bidder.autobid_exhausted', [
                'item_id' => $event->itemPublicId,
                'max_amount_cents' => $event->maxAmount->cents,
            ]),
            $event instanceof ItemAwarded => $this->toBidder($event->bidderId, 'bidder.won', [
                'item_id' => $event->itemPublicId,
                'award_id' => $event->awardPublicId,
                'amount_cents' => $event->amount->cents,
                'title' => Scalar::string(DB::table('auction_items')->where('id', $event->itemId)->value('title')),
            ]),
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function auction(int $auctionId, string $name, array $payload): void
    {
        $publicId = Scalar::string(DB::table('auctions')->where('id', $auctionId)->value('public_id'));
        Broadcast::on(new Channel(self::auctionChannel($publicId)))->as($name)->with(['auction_id' => $publicId] + $payload)->sendNow();
    }

    /** @param array<string, mixed> $payload */
    private function toBidder(int $bidderId, string $name, array $payload): void
    {
        $publicId = Scalar::string(DB::table('bidders')->where('id', $bidderId)->value('public_id'));
        Broadcast::on(new PrivateChannel(self::bidderChannel($publicId)))->as($name)->with($payload)->sendNow();
    }

    /** @return array{id: string, nickname: string} */
    private function bidder(int $bidderId): array
    {
        $row = DB::table('bidders')->where('id', $bidderId)->first(['public_id', 'nickname']);

        return $row === null
            ? ['id' => '', 'nickname' => '']
            : ['id' => Scalar::string($row->public_id), 'nickname' => Scalar::string($row->nickname)];
    }

    public static function auctionChannel(string $auctionPublicId): string
    {
        return "auction.{$auctionPublicId}";
    }

    public static function bidderChannel(string $bidderPublicId): string
    {
        return "bidder.{$bidderPublicId}";
    }

    private static function iso(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
}
