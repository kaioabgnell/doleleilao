<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Infrastructure;

use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Auction\Domain\Event\ItemAwarded;
use DoleLeilao\Auction\Domain\Event\ParticipationConfirmed;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionItemRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\ParticipationRecord;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Identity\Infrastructure\BidderBecameEligible;
use DoleLeilao\Notification\Application\SendNotification;
use DoleLeilao\Notification\Domain\AuctionRef;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Notification\Domain\Recipient;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Infrastructure\Scalar;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avisos ligados aos leilões (RF-N03 a RF-N05, D-43, D-44). O Leilão publica eventos; aqui se
 * decide quem avisar. A `dedupe_key` de cada aviso garante que ninguém recebe o mesmo duas vezes.
 */
final readonly class AuctionNotifications
{
    private const string TIMEZONE = 'America/Sao_Paulo';

    /** Hora local do processo diário (RF-N05). */
    public const int REMINDER_HOUR = 8;

    public function __construct(private SendNotification $send, private Clock $clock) {}

    /** "Quero participar": habilitado (se o cadastro estiver completo), "hoje" se já passou das 8h, e o leiloeiro fica sabendo. */
    public function onParticipation(ParticipationConfirmed $event): void
    {
        $participation = ParticipationRecord::withoutGlobalScope('tenant')->with(['auction' => self::anyStore(...), 'bidder'])->where('public_id', $event->participationPublicId)->first();
        if ($participation === null) {
            return;
        }
        $auction = $participation->auction;
        $bidder = $participation->bidder;

        $this->enable($participation);

        $now = $this->local($this->clock->now());
        if ($this->local($auction->starts_at->toDateTimeImmutable())->format('Y-m-d') === $now->format('Y-m-d') && (int) $now->format('G') >= self::REMINDER_HOUR) {
            $this->send->handle(Notification::auctionToday(Recipient::bidder($bidder->id, $auction->auctioneer_id), $this->ref($auction, forBidder: true)));
        }

        foreach ($this->auctioneerUsers($auction->auctioneer_id) as $user) {
            $this->send->handle(Notification::newParticipant(
                Recipient::auctioneerUser($user->id, $auction->auctioneer_id),
                $this->ref($auction, forBidder: false),
                $bidder->nickname,
                $participation->public_id,
            ));
        }
    }

    /** Cadastro completado depois do "Quero participar": manda o "habilitado" pendente. */
    public function onEligible(BidderBecameEligible $event): void
    {
        $pending = ParticipationRecord::withoutGlobalScope('tenant')
            ->with(['auction' => self::anyStore(...), 'bidder'])
            ->where('bidder_id', $event->bidderId)
            ->whereNull('enabled_notified_at')
            ->get();
        foreach ($pending as $participation) {
            $this->enable($participation);
        }
    }

    /** Vencedor (participante) e item arrematado (leiloeiro). */
    public function onAwarded(ItemAwarded $event): void
    {
        $item = AuctionItemRecord::withoutGlobalScope('tenant')->with(['auction' => self::anyStore(...)])->find($event->itemId);
        if ($item === null) {
            return;
        }
        $auction = $item->auction;

        $this->send->handle(Notification::auctionWon(
            Recipient::bidder($event->bidderId, $auction->auctioneer_id),
            $this->ref($auction, forBidder: true),
            $item->title,
            $event->amount,
            $event->awardedAt,
            $event->awardPublicId,
        ));

        foreach ($this->auctioneerUsers($auction->auctioneer_id) as $user) {
            $this->send->handle(Notification::itemSold(
                Recipient::auctioneerUser($user->id, $auction->auctioneer_id),
                $this->ref($auction, forBidder: false),
                $item->title,
                $event->amount,
                $event->awardPublicId,
            ));
        }
    }

    /**
     * Processo diário das 8h (RF-N05): "amanhã" para os leilões do dia seguinte e "hoje" para os do
     * dia, a quem marcou "Quero participar" e ao leiloeiro. Rodar de novo não duplica nada.
     *
     * @return int avisos novos
     */
    public function sendDailyReminders(): int
    {
        $today = $this->local($this->clock->now())->setTime(0, 0);
        $sent = 0;

        foreach (['today' => $today, 'tomorrow' => $today->modify('+1 day')] as $when => $day) {
            $from = $day->setTimezone(new \DateTimeZone('UTC'));
            $to = $day->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'));

            $auctions = AuctionRecord::withoutGlobalScope('tenant')
                ->whereIn('status', [AuctionStatus::Scheduled, AuctionStatus::Live])
                ->where('starts_at', '>=', $from->format('Y-m-d H:i:s'))
                ->where('starts_at', '<', $to->format('Y-m-d H:i:s'))
                ->get();

            foreach ($auctions as $auction) {
                $make = $when === 'today' ? Notification::auctionToday(...) : Notification::auctionTomorrow(...);
                $participants = ParticipationRecord::withoutGlobalScope('tenant')->where('auction_id', $auction->id)->pluck('bidder_id');
                foreach ($participants as $bidderId) {
                    $sent += (int) $this->send->handle($make(Recipient::bidder(Scalar::int($bidderId), $auction->auctioneer_id), $this->ref($auction, forBidder: true)));
                }
                foreach ($this->auctioneerUsers($auction->auctioneer_id) as $user) {
                    $sent += (int) $this->send->handle($make(Recipient::auctioneerUser($user->id, $auction->auctioneer_id), $this->ref($auction, forBidder: false)));
                }
            }
        }

        return $sent;
    }

    private function enable(ParticipationRecord $participation): void
    {
        $bidder = $participation->bidder;
        $auction = $participation->auction;
        if (! $bidder->isEligible() || ! in_array($auction->status, [AuctionStatus::Scheduled, AuctionStatus::Live], true)) {
            return;
        }
        $this->send->handle(Notification::participationEnabled(Recipient::bidder($bidder->id, $auction->auctioneer_id), $this->ref($auction, forBidder: true)));
        $participation->forceFill(['enabled_notified_at' => $this->clock->now()])->saveQuietly();
    }

    private function ref(AuctionRecord $auction, bool $forBidder): AuctionRef
    {
        $slug = AuctioneerRecord::query()->whereKey($auction->auctioneer_id)->value('slug');

        return new AuctionRef(
            $auction->public_id,
            $auction->name,
            $auction->starts_at->toDateTimeImmutable(),
            $forBidder ? '/'.(is_string($slug) ? $slug : '').'/leilao/'.$auction->slug : '/painel/leiloes/'.$auction->public_id,
        );
    }

    /** @return iterable<AuctioneerUser> */
    private function auctioneerUsers(int $auctioneerId): iterable
    {
        return AuctioneerUser::query()->where('auctioneer_id', $auctioneerId)->get();
    }

    /**
     * Relações carregadas fora de uma requisição (relógio, fila) não têm loja atual.
     *
     * @param  BelongsTo<AuctionRecord, covariant Model>  $query
     */
    private static function anyStore(BelongsTo $query): void
    {
        $query->withoutGlobalScope('tenant');
    }

    private function local(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone(self::TIMEZONE));
    }
}
