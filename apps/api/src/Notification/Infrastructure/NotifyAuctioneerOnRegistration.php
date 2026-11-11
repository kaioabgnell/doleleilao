<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Infrastructure;

use DoleLeilao\Notification\Application\SendNotification;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Notification\Domain\Recipient;
use DoleLeilao\Storefront\Domain\AuctioneerRegistered;

/** "Boas-vindas ao Dole Leilão!" no sino do leiloeiro recém-cadastrado (RF-N04). */
final readonly class NotifyAuctioneerOnRegistration
{
    public function __construct(private SendNotification $send) {}

    public function handle(AuctioneerRegistered $event): void
    {
        $this->send->handle(Notification::welcome(Recipient::auctioneerUser($event->ownerUserId, $event->auctioneerId)));
    }
}
