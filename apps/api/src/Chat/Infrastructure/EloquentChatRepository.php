<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Infrastructure;

use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\EloquentBidderGate;
use DoleLeilao\Chat\Application\Author;
use DoleLeilao\Chat\Application\ChatContext;
use DoleLeilao\Chat\Application\Port\ChatRepository;
use DoleLeilao\Chat\Domain\ChatSettings;
use DoleLeilao\Chat\Domain\MessageBody;
use DoleLeilao\Chat\Domain\Participant;
use DoleLeilao\Chat\Infrastructure\Eloquent\ChatMessageRecord;
use DoleLeilao\Chat\Infrastructure\Eloquent\ChatMuteRecord;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;

/** Lê o lote pelo contexto do Leilão (camada de tradução): o chat só precisa da configuração e do status. */
final class EloquentChatRepository implements ChatRepository
{
    public function context(string $auctionKey): ?ChatContext
    {
        $auction = AuctionRecord::query()
            ->where(fn ($q) => $q->where('slug', $auctionKey)->orWhere('public_id', $auctionKey))
            ->whereNotIn('status', [AuctionStatus::Draft, AuctionStatus::Cancelled])
            ->first();

        return $auction === null ? null : self::contextOf($auction);
    }

    public static function contextOf(AuctionRecord $auction): ChatContext
    {
        $s = $auction->settings;

        return new ChatContext(
            $auction->id,
            $auction->public_id,
            $auction->auctioneer_id,
            ChatSettings::from(
                (bool) ($s['chat_enabled'] ?? true),
                is_string($s['chat_visibility'] ?? null) ? $s['chat_visibility'] : 'everyone',
                is_string($s['chat_write'] ?? null) ? $s['chat_write'] : 'logged_in',
            ),
            in_array($auction->status, [AuctionStatus::Scheduled, AuctionStatus::Live], true),
            $auction->finished_at?->toDateTimeImmutable(),
        );
    }

    public function participant(int $bidderId, ChatContext $context): Participant
    {
        $bidder = BidderUser::query()->find($bidderId);
        if ($bidder === null || $bidder->auctioneer_id !== $context->auctioneerId) {
            return Participant::visitor();
        }
        $mute = ChatMuteRecord::query()->where('auction_id', $context->auctionId)->where('bidder_id', $bidderId)->first();

        return Participant::bidder(
            eligible: $bidder->isEligible(),
            banned: EloquentBidderGate::isBanned($bidder->auctioneer_id, $bidder->id),
            mutedUntil: $mute?->until?->toDateTimeImmutable(),
            mutedForAuction: $mute !== null && $mute->until === null,
        );
    }

    public function replyTarget(int $auctionId, string $messagePublicId): ?int
    {
        $id = ChatMessageRecord::query()->where('auction_id', $auctionId)->where('public_id', $messagePublicId)->whereNull('deleted_at')->value('id');

        return is_int($id) ? $id : null;
    }

    public function add(ChatContext $context, Author $author, MessageBody $body, ?int $replyToId, \DateTimeImmutable $at): string
    {
        return ChatMessageRecord::query()->create([
            'auctioneer_id' => $context->auctioneerId,
            'auction_id' => $context->auctionId,
            'bidder_id' => $author->bidderId,
            'auctioneer_user_id' => $author->auctioneerUserId,
            'body' => $body->value,
            'reply_to_id' => $replyToId,
            'created_at' => $at,
        ])->public_id;
    }

    public function view(string $messagePublicId): array
    {
        return ChatMessageView::of(ChatMessageRecord::query()->with(['bidder', 'replyTo.bidder'])->where('public_id', $messagePublicId)->firstOrFail());
    }

    public function markDeleted(int $auctionId, string $messagePublicId, int $deletedBy, \DateTimeImmutable $at): bool
    {
        return ChatMessageRecord::query()
            ->where('auction_id', $auctionId)
            ->where('public_id', $messagePublicId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $at->format('Y-m-d H:i:s.u'), 'deleted_by' => $deletedBy]) === 1;
    }

    public function bidderIdOf(string $bidderPublicId): ?int
    {
        $id = BidderUser::query()->where('auctioneer_id', app(CurrentTenant::class)->id())
            ->where('public_id', $bidderPublicId)->value('id');

        return is_int($id) ? $id : null;
    }

    public function mute(ChatContext $context, int $bidderId, ?\DateTimeImmutable $until, int $mutedBy): void
    {
        ChatMuteRecord::query()->updateOrCreate(
            ['auction_id' => $context->auctionId, 'bidder_id' => $bidderId],
            ['auctioneer_id' => $context->auctioneerId, 'until' => $until, 'muted_by' => $mutedBy],
        );
    }

    public function unmute(int $auctionId, int $bidderId): bool
    {
        return ChatMuteRecord::query()->where('auction_id', $auctionId)->where('bidder_id', $bidderId)->delete() > 0;
    }
}
