<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\NotificationResource;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Notification\Application\ListNotifications;
use DoleLeilao\Notification\Application\MarkNotificationsRead;
use DoleLeilao\Notification\Domain\RecipientType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Sino do painel e da loja (RF-N01, RF-N02). Cada conta só vê as próprias notificações. */
final class NotificationController
{
    public function index(Request $request, ListNotifications $list): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'string', 'size:26']]);
        $page = $list->handle(
            ...$this->recipient($request),
            cursor: $request->filled('cursor') ? $request->string('cursor')->value() : null,
        );

        return response()->json([
            'data' => NotificationResource::collection($page->items),
            'unread_count' => $page->unreadCount,
            'next_cursor' => $page->nextCursor,
        ]);
    }

    public function read(Request $request, MarkNotificationsRead $mark): JsonResponse
    {
        $request->validate(['ids' => ['sometimes', 'array', 'max:100'], 'ids.*' => ['string', 'size:26']]);

        $ids = $request->has('ids')
            ? array_values($request->collect('ids')->filter(fn (mixed $id): bool => is_string($id))->map(fn (mixed $id): string => (string) $id)->all())
            : null;
        $unread = $mark->handle(...[...$this->recipient($request), 'ids' => $ids]);

        return response()->json(['unread_count' => $unread]);
    }

    /** @return array{type: RecipientType, recipientId: int} */
    private function recipient(Request $request): array
    {
        $user = $request->user('sanctum');

        return match (true) {
            $user instanceof AuctioneerUser => ['type' => RecipientType::AuctioneerUser, 'recipientId' => $user->id],
            $user instanceof BidderUser => ['type' => RecipientType::Bidder, 'recipientId' => $user->id],
            default => abort(401),
        };
    }
}
