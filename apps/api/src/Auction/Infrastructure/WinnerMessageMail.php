<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Mail\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/** E-mail da mensagem ao vencedor (RF-G05), com a marca da loja. Responder vai direto para o contato da loja. */
final class WinnerMessageMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $storeSlug,
        public readonly string $storeName,
        public readonly string $itemTitle,
        public readonly string $body,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(BidderUser $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject("{$this->storeName}: sobre {$this->itemTitle}");
        // line() junta as quebras de linha; a mensagem do leiloeiro vai em parágrafos, com as quebras mantidas.
        foreach (preg_split('/\n{2,}/', $this->body) ?: [] as $paragraph) {
            $message->line(new HtmlString(nl2br(e($paragraph), false)));
        }
        $message
            ->action('Ver minhas arrematações', rtrim(config()->string('doleleilao.frontend_url'), '/')."/{$this->storeSlug}/minha-conta/arrematacoes")
            ->salutation('');

        $contact = DB::table('storefront_settings')->where('auctioneer_id', $notifiable->auctioneer_id)->value('contact_email');
        if (is_string($contact) && $contact !== '') {
            $message->replyTo($contact, $this->storeName);
        }

        return MailBrand::store($notifiable->auctioneer_id)->apply($message);
    }
}
