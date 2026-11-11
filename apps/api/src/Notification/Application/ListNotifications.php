<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Application;

use DoleLeilao\Notification\Application\Port\NotificationRepository;
use DoleLeilao\Notification\Domain\RecipientType;

/** Lista do sino (RF-N02): 20 por vez, mais recentes primeiro. */
final readonly class ListNotifications
{
    public const int PAGE_SIZE = 20;

    public function __construct(private NotificationRepository $notifications) {}

    public function handle(RecipientType $type, int $recipientId, ?string $cursor = null): NotificationPage
    {
        $items = $this->notifications->latest($type, $recipientId, $cursor, self::PAGE_SIZE + 1);
        $hasMore = count($items) > self::PAGE_SIZE;
        $items = array_slice($items, 0, self::PAGE_SIZE);

        return new NotificationPage(
            $items,
            $this->notifications->unreadCount($type, $recipientId),
            $hasMore && $items !== [] ? $items[array_key_last($items)]->id : null,
        );
    }
}
