<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Infrastructure;

use DoleLeilao\Chat\Infrastructure\Eloquent\ChatMessageRecord;

/** Histórico do chat, do mais antigo para o mais novo, em páginas de 50 (rolar para cima carrega mais). */
final class ChatReadModel
{
    public const int PAGE = 50;

    /** @return array{data: list<array<string, mixed>>, has_more: bool} */
    public static function page(int $auctionId, ?string $before, bool $withNames = false): array
    {
        $beforeId = $before === null ? null : ChatMessageRecord::query()->where('auction_id', $auctionId)->where('public_id', $before)->value('id');

        $rows = ChatMessageRecord::query()
            ->with(['bidder', 'replyTo.bidder'])
            ->where('auction_id', $auctionId)
            ->when(is_int($beforeId), fn ($q) => $q->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit(self::PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PAGE;
        $page = $rows->take(self::PAGE)->reverse()->values();

        return [
            'data' => array_values($page->map(fn (ChatMessageRecord $m): array => ChatMessageView::of($m, $withNames))->all()),
            'has_more' => $hasMore,
        ];
    }
}
