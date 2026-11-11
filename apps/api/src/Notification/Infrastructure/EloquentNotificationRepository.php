<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Infrastructure;

use DoleLeilao\Notification\Application\NotificationView;
use DoleLeilao\Notification\Application\Port\NotificationRepository;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Notification\Domain\RecipientType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class EloquentNotificationRepository implements NotificationRepository
{
    public function add(Notification $notification, \DateTimeImmutable $at): bool
    {
        try {
            NotificationRecord::query()->create([
                'recipient_type' => $notification->recipient->type->value,
                'recipient_id' => $notification->recipient->id,
                'auctioneer_id' => $notification->recipient->auctioneerId,
                'kind' => $notification->kind->value,
                'title' => mb_substr($notification->title, 0, 160),
                'body' => mb_substr($notification->body, 0, 500),
                'url' => $notification->url,
                'dedupe_key' => $notification->dedupeKey,
                'created_at' => $at,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false; // o mesmo aviso já foi dado
        }
    }

    public function latest(RecipientType $type, int $recipientId, ?string $cursor, int $limit): array
    {
        $cursorId = $cursor !== null
            ? $this->ownQuery($type, $recipientId)->where('public_id', $cursor)->value('id')
            : null;

        return array_values($this->ownQuery($type, $recipientId)
            ->when($cursorId !== null, fn ($q) => $q->where('id', '<', $cursorId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (NotificationRecord $r): NotificationView => new NotificationView(
                $r->public_id,
                $r->kind,
                $r->title,
                $r->body,
                $r->url,
                $r->read_at?->toDateTimeImmutable(),
                $r->created_at->toDateTimeImmutable(),
            ))
            ->all());
    }

    public function unreadCount(RecipientType $type, int $recipientId): int
    {
        return $this->ownQuery($type, $recipientId)->whereNull('read_at')->count();
    }

    public function markRead(RecipientType $type, int $recipientId, ?array $publicIds, \DateTimeImmutable $at): void
    {
        $this->ownQuery($type, $recipientId)
            ->whereNull('read_at')
            ->when($publicIds !== null, fn ($q) => $q->whereIn('public_id', $publicIds ?? []))
            ->update(['read_at' => $at]);
    }

    /** @return Builder<NotificationRecord> */
    private function ownQuery(RecipientType $type, int $recipientId): Builder
    {
        return NotificationRecord::query()
            ->where('recipient_type', $type->value)
            ->where('recipient_id', $recipientId);
    }
}
