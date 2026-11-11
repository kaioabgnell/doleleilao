<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Application;

final readonly class NotificationPage
{
    /** @param list<NotificationView> $items */
    public function __construct(
        public array $items,
        public int $unreadCount,
        public ?string $nextCursor,
    ) {}
}
