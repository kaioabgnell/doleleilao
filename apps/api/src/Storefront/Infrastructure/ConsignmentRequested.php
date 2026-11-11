<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure;

use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Infrastructure\Mail\MailBrand;
use DoleLeilao\Storefront\Infrastructure\Eloquent\ConsignmentRequestRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail ao leiloeiro: chegou um pedido "quero leiloar" (RF-E09). */
final class ConsignmentRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ConsignmentRequestRecord $consignment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->consignment;
        $message = (new MailMessage)
            ->subject("Novo pedido para leiloar: {$c->name}")
            ->greeting('Chegou um pedido pela sua loja')
            ->line("{$c->name} quer leiloar um produto com você:")
            ->line('“'.mb_strimwidth($c->description, 0, 400, '…').'”');
        if ($c->email !== null) {
            $message->line("E-mail: {$c->email}");
        }
        if ($c->phone !== null) {
            $message->line('Telefone/WhatsApp: '.Phone::fromString($c->phone)->formatted());
        }

        return MailBrand::platform()->apply($message
            ->action('Ver no painel', rtrim(config()->string('doleleilao.frontend_url'), '/').'/painel/pedidos')
            ->salutation('Equipe Dole Leilão'));
    }
}
