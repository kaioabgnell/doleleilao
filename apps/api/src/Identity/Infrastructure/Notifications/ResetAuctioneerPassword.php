<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure\Notifications;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Shared\Infrastructure\Mail\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Recuperação de senha do leiloeiro (RF-A05). O link vale 60 minutos. */
final class ResetAuctioneerPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AuctioneerUser $notifiable): MailMessage
    {
        return MailBrand::platform()->apply((new MailMessage)
            ->subject('Crie uma nova senha no Dole Leilão')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Recebemos um pedido para criar uma nova senha para a sua conta.')
            ->action('Criar nova senha', $this->url($notifiable))
            ->line('O link vale por 60 minutos. Se você não pediu, ignore esta mensagem: sua senha continua a mesma.')
            ->salutation('Equipe Dole Leilão'));
    }

    public function url(AuctioneerUser $notifiable): string
    {
        $frontend = rtrim(config()->string('doleleilao.frontend_url'), '/');

        return $frontend.'/redefinir-senha?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
