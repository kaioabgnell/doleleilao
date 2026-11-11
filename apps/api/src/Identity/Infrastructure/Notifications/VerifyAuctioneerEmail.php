<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure\Notifications;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Shared\Infrastructure\Mail\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Validação do e-mail do leiloeiro (RF-A04). O link vale 24 horas. */
final class VerifyAuctioneerEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public const int EXPIRES_IN_MINUTES = 60 * 24;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AuctioneerUser $notifiable): MailMessage
    {
        return MailBrand::platform()->apply((new MailMessage)
            ->subject('Confirme seu e-mail no Dole Leilão')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Falta pouco para sua loja ficar no ar. Confirme seu e-mail tocando no botão abaixo.')
            ->action('Confirmar e-mail', self::url($notifiable))
            ->line('O link vale por 24 horas. Se você não criou uma conta no Dole Leilão, ignore esta mensagem.')
            ->salutation('Equipe Dole Leilão'));
    }

    public static function url(AuctioneerUser $user): string
    {
        return URL::temporarySignedRoute(
            'api.v1.auctioneer.email.verify',
            now()->addMinutes(self::EXPIRES_IN_MINUTES),
            ['user' => $user->public_id, 'hash' => sha1($user->getEmailForVerification())],
        );
    }
}
