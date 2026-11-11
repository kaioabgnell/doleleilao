<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure;

use DoleLeilao\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Banir e desbanir na loja (RF-G02): efeito imediato. O participante perde a sessão (tokens
 * revogados), os lances automáticos dele são desligados, ele não dá lance, não escreve no chat e
 * não entra mais na conta desta loja. O auditório dele recebe `bidder.banned` (ParticipanteBanido).
 */
final readonly class BidderBans
{
    public function __construct(private Clock $clock) {}

    public function ban(BidderUser $bidder, string $reason, ?int $bannedBy): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new InvalidBan('reason', 'Informe o motivo do banimento.');
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s.u');
        DB::transaction(function () use ($bidder, $reason, $bannedBy, $now): void {
            if (! $this->isBanned($bidder)) {
                DB::table('bans')->insert([
                    'auctioneer_id' => $bidder->auctioneer_id,
                    'bidder_id' => $bidder->id,
                    'reason' => mb_substr($reason, 0, 300),
                    'banned_by' => $bannedBy,
                    'banned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('auto_bids')->where('bidder_id', $bidder->id)->where('active', true)->update(['active' => false, 'updated_at' => $now]);
            $bidder->tokens()->delete();
        });

        event(new BidderBanned($bidder->id, $bidder->auctioneer_id));
    }

    public function lift(BidderUser $bidder): void
    {
        DB::table('bans')->where('auctioneer_id', $bidder->auctioneer_id)->where('bidder_id', $bidder->id)->whereNull('lifted_at')
            ->update(['lifted_at' => $this->clock->now()->format('Y-m-d H:i:s.u')]);
    }

    public function isBanned(BidderUser $bidder): bool
    {
        return DB::table('bans')->where('auctioneer_id', $bidder->auctioneer_id)->where('bidder_id', $bidder->id)->whereNull('lifted_at')->exists();
    }
}
