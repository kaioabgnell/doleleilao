<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Infrastructure;

use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Mail\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerta por e-mail "o leilão vai começar" (RF-E06), com a marca da loja. */
final class AuctionStartingSoonMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $auctionName,
        public readonly string $startsAtLocal,
        public readonly int $minutes,
        public readonly string $url,
        public readonly int $itemsCount,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(BidderUser $notifiable): MailMessage
    {
        $store = $notifiable->auctioneer->display_name;

        return MailBrand::store($notifiable->auctioneer_id)->apply((new MailMessage)
            ->subject("O leilão {$this->auctionName} começa em {$this->minutes} minutos")
            ->greeting("Olá, {$notifiable->nickname}!")
            ->line("O leilão {$this->auctionName} começa às {$this->startsAtLocal} (horário de Brasília), com {$this->itemsCount} ".($this->itemsCount === 1 ? 'item' : 'itens').'.')
            ->line('Entre alguns minutos antes para não perder o primeiro item.')
            ->action('Entrar no auditório', $this->url)
            ->line('Você recebe este aviso porque marcou "Quero participar" ou favoritou um item deste leilão. Dá para mudar em "Minha conta".')
            ->salutation($store));
    }
}
