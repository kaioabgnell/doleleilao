<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Application\Actor;
use DoleLeilao\Auction\Application\Port\AuditLog;
use Illuminate\Support\Facades\DB;

final class DatabaseAuditLog implements AuditLog
{
    public function record(int $auctionId, Actor $actor, string $action, array $payload, \DateTimeImmutable $at): void
    {
        DB::table('auction_audit_log')->insert([
            'auctioneer_id' => DB::table('auctions')->where('id', $auctionId)->value('auctioneer_id'),
            'auction_id' => $auctionId,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'action' => $action,
            'payload' => json_encode((object) $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $at->format('Y-m-d H:i:s.u'),
        ]);
    }
}
