<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Domain\InvalidAuction;
use DoleLeilao\Auction\Domain\WinnerMessageTemplate;
use DoleLeilao\Auction\Infrastructure\Eloquent\AwardRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\WinnerMessageRecord;
use DoleLeilao\Notification\Application\SendNotification;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Notification\Domain\Recipient;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Domain\Money;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Support\Facades\DB;

/**
 * Mensagem ao vencedor (RF-G05, D-25): modelo editável da loja, envio por e-mail + "Minha conta" +
 * sino, e o link do WhatsApp com o texto pronto (sem API paga: abre no aparelho do leiloeiro).
 */
final readonly class WinnerMessages
{
    public const array CHANNELS = ['email', 'whatsapp'];

    public function __construct(private SendNotification $send, private Clock $clock) {}

    public function template(int $auctioneerId): WinnerMessageTemplate
    {
        $text = DB::table('storefront_settings')->where('auctioneer_id', $auctioneerId)->value('winner_message_template');

        return is_string($text) && $text !== '' ? WinnerMessageTemplate::from($text) : WinnerMessageTemplate::default();
    }

    public function saveTemplate(int $auctioneerId, ?string $text): WinnerMessageTemplate
    {
        $template = $text === null ? WinnerMessageTemplate::default() : WinnerMessageTemplate::from($text);
        DB::table('storefront_settings')->where('auctioneer_id', $auctioneerId)
            ->update(['winner_message_template' => $text === null ? null : $template->text]);

        return $template;
    }

    /** @return array<string, string> os campos do modelo preenchidos com esta arrematação */
    public function values(AwardRecord $award): array
    {
        $award->loadMissing(['bidder', 'item.auction']);
        $store = DB::table('auctioneers')->join('storefront_settings', 'storefront_settings.auctioneer_id', '=', 'auctioneers.id')
            ->where('auctioneers.id', $award->auctioneer_id)->first(['auctioneers.display_name', 'storefront_settings.contact_whatsapp']);
        $phone = $store === null ? null : $store->contact_whatsapp;
        $whatsapp = is_string($phone) ? Phone::fromString($phone)->formatted() : '';

        return [
            'nome' => $award->bidder->name,
            'apelido' => $award->bidder->nickname,
            'item' => $award->item->title,
            'valor' => Money::fromCents($award->amount)->format(),
            'leilao' => $award->item->auction->name,
            'data' => $award->awarded_at->setTimezone('America/Sao_Paulo')->format('d/m/Y'),
            'loja' => $store === null ? '' : Scalar::string($store->display_name),
            'whatsapp' => $whatsapp,
        ];
    }

    public function draft(AwardRecord $award): string
    {
        return $this->template($award->auctioneer_id)->render($this->values($award));
    }

    /**
     * @param  list<string>  $channels
     * @return array{message: WinnerMessageRecord, whatsapp_url: string}
     */
    public function send(AwardRecord $award, string $body, array $channels, ?int $auctioneerUserId): array
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", $body));
        if ($body === '' || mb_strlen($body) > WinnerMessageTemplate::MAX) {
            throw new InvalidAuction('body', $body === '' ? 'Escreva a mensagem.' : 'A mensagem pode ter até '.WinnerMessageTemplate::MAX.' caracteres.');
        }
        $channels = array_values(array_intersect(self::CHANNELS, $channels));
        if ($channels === []) {
            throw new InvalidAuction('channels', 'Escolha como enviar: e-mail ou WhatsApp.');
        }

        $award->loadMissing(['bidder.auctioneer', 'item']);
        $message = WinnerMessageRecord::query()->create([
            'auctioneer_id' => $award->auctioneer_id,
            'award_id' => $award->id,
            'auctioneer_user_id' => $auctioneerUserId,
            'body' => $body,
            'channels' => $channels,
            'sent_at' => $this->clock->now(),
        ]);

        $bidder = $award->bidder;
        $storeName = $bidder->auctioneer->display_name;
        if (in_array('email', $channels, true)) {
            $bidder->notify(new WinnerMessageMail($bidder->auctioneer->slug, $storeName, $award->item->title, $body));
        }
        // Sempre aparece no sino e em "Minha conta", mesmo quando o leiloeiro usou só o WhatsApp.
        $this->send->handle(Notification::winnerMessage(
            Recipient::bidder($bidder->id, $award->auctioneer_id),
            $storeName,
            $award->item->title,
            "/{$bidder->auctioneer->slug}/minha-conta/arrematacoes",
            $message->public_id,
        ));

        return ['message' => $message, 'whatsapp_url' => self::whatsappUrl($bidder->phone, $body)];
    }

    /** https://wa.me/55{DDD+número}?text=… (D-25). */
    public static function whatsappUrl(string $phone, string $text): string
    {
        return 'https://wa.me/55'.preg_replace('/\D/', '', $phone).'?text='.rawurlencode($text);
    }
}
