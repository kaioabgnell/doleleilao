<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Application;

final readonly class NotificationView
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        public ?\DateTimeImmutable $readAt,
        public \DateTimeImmutable $createdAt,
    ) {}
}
