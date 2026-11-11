<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Application;

use DoleLeilao\Notification\Application\Port\NotificationRepository;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Shared\Domain\Clock;

/** Coloca o aviso no sino. Repetido (mesma dedupe_key) é ignorado. */
final readonly class SendNotification
{
    public function __construct(
        private NotificationRepository $notifications,
        private Clock $clock,
    ) {}

    public function handle(Notification $notification): bool
    {
        return $this->notifications->add($notification, $this->clock->now());
    }
}
