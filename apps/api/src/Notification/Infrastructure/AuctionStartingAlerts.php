<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Infrastructure;

use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Alertas "o leilão vai começar" por e-mail (RF-E06): para quem marcou "Quero participar" ou
 * favoritou um item do lote, no prazo que cada um escolheu em "Minha conta" (padrão 30 min).
 * Roda a cada minuto; um alerta por pessoa por leilão (auction_alerts).
 */
final readonly class AuctionStartingAlerts
{
    public const array OPTIONS = [15, 30, 60, 120];

    public function __construct(private Clock $clock) {}

    /** "20h" ou "19h30", no horário de Brasília. */
    private static function hour(\DateTimeImmutable $at): string
    {
        $local = $at->setTimezone(new \DateTimeZone('America/Sao_Paulo'));

        return $local->format('i') === '00' ? $local->format('G\h') : $local->format('G\hi');
    }

    /** @return int e-mails enviados */
    public function send(): int
    {
        $now = $this->clock->now();
        $horizon = $now->modify('+'.max(self::OPTIONS).' minutes');
        $sent = 0;

        $auctions = AuctionRecord::withoutGlobalScope('tenant')
            ->where('status', AuctionStatus::Scheduled)
            ->where('starts_at', '>', $now->format('Y-m-d H:i:s.u'))
            ->where('starts_at', '<=', $horizon->format('Y-m-d H:i:s.u'))
            ->withCount(['items' => fn (EloquentBuilder $q) => $q->withoutGlobalScope('tenant')])
            ->get();

        foreach ($auctions as $auction) {
            $minutesLeft = (int) ceil(($auction->starts_at->getTimestamp() - $now->getTimestamp()) / 60);
            $slug = Scalar::string(DB::table('auctioneers')->where('id', $auction->auctioneer_id)->value('slug'));

            $participants = DB::table('auction_participations')->where('auction_id', $auction->id)->pluck('bidder_id');
            $favoriters = DB::table('favorites')
                ->join('auction_items', 'auction_items.product_id', '=', 'favorites.product_id')
                ->where('auction_items.auction_id', $auction->id)
                ->pluck('favorites.bidder_id');

            $bidders = BidderUser::query()
                ->where('auctioneer_id', $auction->auctioneer_id)
                ->whereIn('id', $participants->merge($favoriters)->unique()->values())
                ->whereNotNull('email_verified_at')
                ->whereNotNull('alert_minutes')
                ->where('alert_minutes', '>=', $minutesLeft)
                ->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))->from('auction_alerts')
                    ->whereColumn('auction_alerts.bidder_id', 'bidders.id')->where('auction_alerts.auction_id', $auction->id))
                ->get();

            foreach ($bidders as $bidder) {
                $inserted = DB::table('auction_alerts')->insertOrIgnore([
                    'auctioneer_id' => $auction->auctioneer_id,
                    'auction_id' => $auction->id,
                    'bidder_id' => $bidder->id,
                    'minutes_before' => $bidder->alert_minutes,
                    'sent_at' => $now->format('Y-m-d H:i:s.u'),
                ]);
                if ($inserted === 0) {
                    continue; // outro processo já avisou
                }
                $bidder->notify(new AuctionStartingSoonMail(
                    $auction->name,
                    self::hour($auction->starts_at->toDateTimeImmutable()),
                    max(1, $minutesLeft),
                    rtrim(config()->string('doleleilao.frontend_url'), '/')."/{$slug}/leilao/{$auction->slug}",
                    Scalar::int($auction->getAttribute('items_count')),
                ));
                $sent++;
            }
        }

        return $sent;
    }
}
