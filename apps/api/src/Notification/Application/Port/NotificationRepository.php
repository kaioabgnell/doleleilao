<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Application\Port;

use DoleLeilao\Notification\Application\NotificationView;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Notification\Domain\RecipientType;

interface NotificationRepository
{
    /** Grava o aviso. Devolve false se o mesmo aviso (dedupe_key) já existe. */
    public function add(Notification $notification, \DateTimeImmutable $at): bool;

    /** @return list<NotificationView> mais recentes primeiro, antes do cursor (id público) */
    public function latest(RecipientType $type, int $recipientId, ?string $cursor, int $limit): array;

    public function unreadCount(RecipientType $type, int $recipientId): int;

    /** @param list<string>|null $publicIds null = todas */
    public function markRead(RecipientType $type, int $recipientId, ?array $publicIds, \DateTimeImmutable $at): void;
}
