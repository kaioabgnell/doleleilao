<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Infrastructure;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Notification\Application\SendNotification;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Notification\Domain\Recipient;
use DoleLeilao\Storefront\Domain\StorefrontPublished;

/** "Sua loja está no ar" para as contas do painel da loja (RF-N04). */
final readonly class NotifyAuctioneerOnStorefrontPublished
{
    public function __construct(private SendNotification $send) {}

    public function handle(StorefrontPublished $event): void
    {
        $host = (string) preg_replace('#^https?://#', '', rtrim(config()->string('doleleilao.frontend_url'), '/'));

        foreach (AuctioneerUser::query()->where('auctioneer_id', $event->auctioneerId)->get() as $user) {
            $this->send->handle(Notification::storeLive(
                Recipient::auctioneerUser($user->id, $event->auctioneerId),
                "{$host}/{$event->slug}",
            ));
        }
    }
}
