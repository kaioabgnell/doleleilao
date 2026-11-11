<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure\Notifications;

use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Mail\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Nova senha do participante. Link para a página da própria loja, válido por 60 min. */
final class ResetBidderPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token, public readonly string $storeSlug) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(BidderUser $notifiable): MailMessage
    {
        $store = $notifiable->auctioneer->display_name;

        return MailBrand::store($notifiable->auctioneer_id)->apply((new MailMessage)
            ->subject("Crie uma nova senha na loja {$store}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Recebemos um pedido para criar uma nova senha.')
            ->action('Criar nova senha', $this->url($notifiable))
            ->line('O link vale por 60 minutos. Se você não pediu, ignore esta mensagem.')
            ->salutation($store));
    }

    public function url(BidderUser $notifiable): string
    {
        return rtrim(config()->string('doleleilao.frontend_url'), '/')."/{$this->storeSlug}/redefinir-senha?"
            .http_build_query(['token' => $this->token, 'email' => $notifiable->email]);
    }
}
