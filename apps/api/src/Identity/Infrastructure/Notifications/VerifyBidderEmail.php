<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure\Notifications;

use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Mail\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Validação do e-mail do participante (RF-E04), com o nome da loja. Link válido por 24 h. */
final class VerifyBidderEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $storeSlug) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(BidderUser $notifiable): MailMessage
    {
        $store = $notifiable->auctioneer->display_name;

        return MailBrand::store($notifiable->auctioneer_id)->apply((new MailMessage)
            ->subject("Confirme seu e-mail na loja {$store}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Confirme seu e-mail para dar lances nos leilões da {$store}.")
            ->action('Confirmar e-mail', self::url($notifiable))
            ->line('O link vale por 24 horas. Se você não criou esta conta, ignore esta mensagem.')
            ->salutation($store));
    }

    public static function url(BidderUser $bidder): string
    {
        return URL::temporarySignedRoute(
            'api.v1.bidders.email.verify',
            now()->addDay(),
            ['bidder' => $bidder->public_id, 'hash' => sha1($bidder->email)],
        );
    }
}
