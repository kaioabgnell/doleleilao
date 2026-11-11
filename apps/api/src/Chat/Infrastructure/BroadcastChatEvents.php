<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Infrastructure;

use DoleLeilao\Auction\Infrastructure\BroadcastAuctionEvents;
use DoleLeilao\Auction\Infrastructure\RealtimeHealth;
use DoleLeilao\Chat\Application\BidderMuted;
use DoleLeilao\Chat\Application\MessageDeleted;
use DoleLeilao\Chat\Application\MessagePosted;
use DoleLeilao\Identity\Infrastructure\BidderBanned;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

/**
 * Chat no Reverb: `auction.{id}.chat` (público) ou `private-auction.{id}.chat` (só logados, RF-D03).
 * Avisos de moderação vão para o canal privado do participante.
 */
final class BroadcastChatEvents
{
    public function handle(MessagePosted|MessageDeleted|BidderMuted|BidderBanned $event): void
    {
        try {
            match (true) {
                $event instanceof MessagePosted => Broadcast::on(self::channel($event->auctionPublicId, $event->privateChannel))
                    ->as('chat.message')->with($event->message)->sendNow(),
                $event instanceof MessageDeleted => Broadcast::on(self::channel($event->auctionPublicId, $event->privateChannel))
                    ->as('chat.message_deleted')->with(['id' => $event->messagePublicId])->sendNow(),
                $event instanceof BidderMuted => $this->toBidder($event->bidderId, $event->muted ? 'bidder.muted' : 'bidder.unmuted', [
                    'auction_id' => $event->auctionPublicId,
                    'until' => $event->until?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
                ]),
                $event instanceof BidderBanned => $this->toBidder($event->bidderId, 'bidder.banned', []),
            };
        } catch (\Throwable $e) {
            RealtimeHealth::recordFailure();
            report($e);
        }
    }

    public static function channel(string $auctionPublicId, bool $private): Channel
    {
        return $private ? new PrivateChannel(self::name($auctionPublicId)) : new Channel(self::name($auctionPublicId));
    }

    public static function name(string $auctionPublicId): string
    {
        return "auction.{$auctionPublicId}.chat";
    }

    /** @param array<string, mixed> $payload */
    private function toBidder(int $bidderId, string $name, array $payload): void
    {
        $publicId = Scalar::string(DB::table('bidders')->where('id', $bidderId)->value('public_id'));
        Broadcast::on(new PrivateChannel(BroadcastAuctionEvents::bidderChannel($publicId)))->as($name)->with($payload)->sendNow();
    }
}
