<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Infrastructure;

use DoleLeilao\Chat\Domain\MessageBody;
use DoleLeilao\Chat\Infrastructure\Eloquent\ChatMessageRecord;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Support\Facades\DB;

/**
 * A mensagem como sai na API e no WebSocket (specs/06, evento chat.message): autor com apelido e
 * foto/avatar (o leiloeiro aparece com o nome de exibição da loja), citação e se foi apagada.
 */
final class ChatMessageView
{
    /** @var array<int, string> */
    private static array $storeNames = [];

    /** @return array<string, mixed> */
    public static function of(ChatMessageRecord $m, bool $withNames = false): array
    {
        $deleted = $m->deleted_at !== null;
        $reply = $m->reply_to_id !== null ? $m->replyTo : null;

        return [
            'id' => $m->public_id,
            'body' => $deleted ? null : $m->body,
            'deleted' => $deleted,
            'created_at' => $m->created_at->setTimezone('UTC')->format('Y-m-d\TH:i:s.v\Z'),
            'author' => self::author($m, $withNames),
            'reply_to' => $reply === null ? null : [
                'id' => $reply->public_id,
                'author' => self::author($reply, false)['nickname'],
                'excerpt' => $reply->deleted_at !== null ? null : MessageBody::cut($reply->body),
                'deleted' => $reply->deleted_at !== null,
            ],
        ];
    }

    /** @return array{type: string, id: string|null, nickname: string, avatar_key: string|null, photo_url: string|null, name?: string} */
    private static function author(ChatMessageRecord $m, bool $withNames): array
    {
        if ($m->bidder_id !== null && $m->bidder !== null) {
            $b = $m->bidder;

            return [
                'type' => 'bidder',
                'id' => $b->public_id,
                'nickname' => $b->nickname,
                'avatar_key' => $b->avatar_key,
                'photo_url' => app(MediaStorage::class)->url($b->photo_path),
            ] + ($withNames ? ['name' => $b->name] : []);
        }

        return [
            'type' => 'auctioneer',
            'id' => null,
            'nickname' => self::storeName($m->auctioneer_id),
            'avatar_key' => null,
            'photo_url' => null,
        ];
    }

    private static function storeName(int $auctioneerId): string
    {
        $name = self::$storeNames[$auctioneerId] ??= Scalar::string(DB::table('auctioneers')->where('id', $auctioneerId)->value('display_name') ?? '');

        return $name !== '' ? $name : 'Leiloeiro';
    }
}
