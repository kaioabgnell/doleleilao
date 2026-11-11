<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use DoleLeilao\Chat\Application\ChatContext;
use DoleLeilao\Chat\Application\ChatNotFound;
use DoleLeilao\Chat\Domain\ChatAccess;
use DoleLeilao\Chat\Domain\Participant;
use DoleLeilao\Chat\Infrastructure\BroadcastChatEvents;
use DoleLeilao\Chat\Infrastructure\ChatReadModel;
use DoleLeilao\Chat\Infrastructure\EloquentChatRepository;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Domain\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Histórico do chat na loja (RF-F05) e o que a pessoa pode fazer (RF-D03, RF-F10). O token do
 * participante é opcional: sem ele, é um visitante.
 */
final class ChatController
{
    use ResolvesStore;

    public function __construct(private readonly EloquentChatRepository $chat, private readonly Clock $clock) {}

    public function index(Request $request, string $slug, string $auction): JsonResponse
    {
        $store = $this->resolveStore($slug);
        $request->validate(['before' => ['nullable', 'string', 'size:26']]);
        $context = $this->chat->context($auction) ?? throw new ChatNotFound;

        $user = $request->user('sanctum');
        $who = $user instanceof BidderUser && $user->auctioneer_id === $store->id && $user->tokenCan(BidderUser::abilityFor($store->id))
            ? $this->chat->participant($user->id, $context)
            : Participant::visitor();

        $now = $this->clock->now();
        $canRead = ChatAccess::canRead($context->settings, $who) && ChatAccess::historyVisible($context->finishedAt, $now);
        $page = $canRead ? ChatReadModel::page($context->auctionId, $request->filled('before') ? $request->string('before')->value() : null) : ['data' => [], 'has_more' => false];

        return response()->json($page + ['access' => self::access($context, $who, $now)]);
    }

    /** @return array<string, mixed> */
    public static function access(ChatContext $context, Participant $who, \DateTimeImmutable $now): array
    {
        $denied = ChatAccess::writeDenial($context->settings, $who, $context->open, $now);
        // Passadas 12 h do fim, o histórico some da loja (o motivo vai junto, para a tela explicar).
        $expired = ! ChatAccess::historyVisible($context->finishedAt, $now);

        return [
            'enabled' => $context->settings->enabled,
            'can_read' => ChatAccess::canRead($context->settings, $who) && ! $expired,
            'can_write' => $denied === null,
            'reason' => $expired ? 'expired' : $denied?->value,
            'message' => $expired ? 'O chat deste leilão ficou disponível por '.ChatAccess::HISTORY_HOURS.' horas depois do fim.' : $denied?->message(),
            'muted_until' => $who->mutedUntil !== null && $now < $who->mutedUntil ? $who->mutedUntil->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z') : null,
            'channel' => ['name' => BroadcastChatEvents::name($context->auctionPublicId), 'private' => $context->settings->loggedInOnly],
        ];
    }
}
