<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Problem;
use DoleLeilao\Auction\Infrastructure\BroadcastAuctionEvents;
use DoleLeilao\Auction\Infrastructure\EloquentBidderGate;
use DoleLeilao\Chat\Infrastructure\BroadcastChatEvents;
use DoleLeilao\Chat\Infrastructure\EloquentChatRepository;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autoriza canais privados do WebSocket (protocolo Pusher):
 * - `private-bidder.{id}`: só o próprio participante, nunca banido;
 * - `private-auction.{id}.chat` (chat só para logados, RF-D03): participante da loja não banido, ou o leiloeiro.
 * Os canais `auction.*` e o chat aberto a todos são públicos.
 */
final class BroadcastAuthController
{
    public function __construct(private readonly EloquentChatRepository $chat) {}

    public function bidder(Request $request): JsonResponse
    {
        $channel = $this->channel($request);
        /** @var BidderUser $bidder */
        $bidder = $request->user('sanctum');

        if (EloquentBidderGate::isBanned($bidder->auctioneer_id, $bidder->id)) {
            return Problem::make(403, 'Sua conta está bloqueada nesta loja.');
        }
        $allowed = $channel === 'private-'.BroadcastAuctionEvents::bidderChannel($bidder->public_id)
            || $this->isStoreChat($channel);

        return $allowed ? $this->sign($request, $channel) : Problem::make(403, 'Canal não autorizado.');
    }

    public function auctioneer(Request $request): JsonResponse
    {
        $channel = $this->channel($request);

        return $this->isStoreChat($channel) ? $this->sign($request, $channel) : Problem::make(403, 'Canal não autorizado.');
    }

    /** Chat privado de um lote da loja atual (o tenant vem do token). */
    private function isStoreChat(string $channel): bool
    {
        if (preg_match('/^private-auction\.([0-9a-z]{26})\.chat$/', $channel, $m) !== 1) {
            return false;
        }
        $context = $this->chat->context($m[1]);

        return $context !== null && $context->settings->loggedInOnly && 'private-'.BroadcastChatEvents::name($context->auctionPublicId) === $channel;
    }

    private function channel(Request $request): string
    {
        $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:100'],
        ]);

        return $request->string('channel_name')->value();
    }

    private function sign(Request $request, string $channel): JsonResponse
    {
        $key = config()->string('broadcasting.connections.reverb.key');
        $secret = config()->string('broadcasting.connections.reverb.secret');
        $signature = hash_hmac('sha256', $request->string('socket_id')->value().':'.$channel, $secret);

        return response()->json(['auth' => "{$key}:{$signature}"]);
    }
}
