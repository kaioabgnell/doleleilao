<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Application;

use DoleLeilao\Notification\Application\Port\NotificationRepository;
use DoleLeilao\Notification\Domain\RecipientType;
use DoleLeilao\Shared\Domain\Clock;

final readonly class MarkNotificationsRead
{
    public function __construct(
        private NotificationRepository $notifications,
        private Clock $clock,
    ) {}

    /** @param list<string>|null $ids null = todas */
    public function handle(RecipientType $type, int $recipientId, ?array $ids = null): int
    {
        $this->notifications->markRead($type, $recipientId, $ids, $this->clock->now());

        return $this->notifications->unreadCount($type, $recipientId);
    }
}
