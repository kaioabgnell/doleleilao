<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Domain;

use DoleLeilao\Shared\Domain\Money;

/**
 * Aviso do sino (D-43). Os textos ficam todos aqui, em pt-BR, e as datas aparecem no horário de Brasília.
 * `dedupeKey` impede que o mesmo aviso seja criado duas vezes (RF-N05).
 */
final readonly class Notification
{
    private const string TIMEZONE = 'America/Sao_Paulo';

    private function __construct(
        public Recipient $recipient,
        public NotificationKind $kind,
        public string $title,
        public string $body,
        public string $url,
        public string $dedupeKey,
    ) {
        if (! in_array($recipient->type, $kind->audience(), true)) {
            throw new \InvalidArgumentException("{$kind->value} é só para ".implode(', ', array_map(
                static fn (RecipientType $t): string => $t->value,
                $kind->audience(),
            )).'.');
        }
    }

    public static function welcome(Recipient $to): self
    {
        return new self($to, NotificationKind::Welcome, 'Boas-vindas ao Dole Leilão!',
            'Siga os primeiros passos para agendar seu primeiro leilão.', '/painel', 'welcome:'.$to->key());
    }

    public static function storeLive(Recipient $to, string $storeAddress): self
    {
        return new self($to, NotificationKind::StoreLive, 'Sua loja está no ar',
            "Compartilhe o link com seus clientes: {$storeAddress}.", '/painel', 'store_live:'.$to->key());
    }

    public static function auctionTomorrow(Recipient $to, AuctionRef $auction): self
    {
        return self::reminder($to, $auction, NotificationKind::AuctionTomorrow, 'amanhã');
    }

    public static function auctionToday(Recipient $to, AuctionRef $auction): self
    {
        return self::reminder($to, $auction, NotificationKind::AuctionToday, 'hoje');
    }

    public static function auctionWon(
        Recipient $to,
        AuctionRef $auction,
        string $itemTitle,
        Money $amount,
        \DateTimeImmutable $wonAt,
        string $awardRef,
    ): self {
        return new self($to, NotificationKind::AuctionWon, "Você foi o vencedor no leilão {$auction->name}",
            sprintf('Em %s você arrematou %s por %s.', self::date($wonAt), $itemTitle, $amount->format()),
            $auction->url, 'auction_won:'.$awardRef);
    }

    public static function participationEnabled(Recipient $to, AuctionRef $auction): self
    {
        return new self($to, NotificationKind::ParticipationEnabled,
            "Seu cadastro está habilitado para participar do leilão {$auction->name}",
            sprintf('Começa em %s às %s. Vamos avisar na véspera e no dia.', self::date($auction->startsAt), self::hour($auction->startsAt)),
            $auction->url, "participation_enabled:{$auction->publicId}:".$to->key());
    }

    public static function itemSold(Recipient $to, AuctionRef $auction, string $itemTitle, Money $amount, string $awardRef): self
    {
        return new self($to, NotificationKind::ItemSold, "{$itemTitle} foi arrematado por {$amount->format()}",
            "No leilão {$auction->name}.", $auction->url, 'item_sold:'.$awardRef.':'.$to->key());
    }

    public static function newParticipant(Recipient $to, AuctionRef $auction, string $bidderName, string $participationRef): self
    {
        return new self($to, NotificationKind::NewParticipant, "Novo participante no leilão {$auction->name}",
            "{$bidderName} vai participar.", $auction->url, 'new_participant:'.$participationRef.':'.$to->key());
    }

    /** Mensagem do leiloeiro sobre uma arrematação (RF-G05); cada mensagem vira um aviso. */
    public static function winnerMessage(Recipient $to, string $storeName, string $itemTitle, string $url, string $messageRef): self
    {
        return new self($to, NotificationKind::WinnerMessage, "Mensagem de {$storeName}",
            "Sobre {$itemTitle}. Toque para ler.", $url, 'winner_message:'.$messageRef);
    }

    public static function consignmentRequest(Recipient $to, string $requesterName, string $requestRef): self
    {
        return new self($to, NotificationKind::ConsignmentRequest, 'Novo pedido para leiloar',
            "{$requesterName} quer leiloar um produto com você.", '/painel/pedidos', 'consignment_request:'.$requestRef.':'.$to->key());
    }

    private static function reminder(Recipient $to, AuctionRef $auction, NotificationKind $kind, string $when): self
    {
        $isBidder = $to->type === RecipientType::Bidder;

        return new self(
            $to,
            $kind,
            ($isBidder ? 'O leilão ' : 'Seu leilão ')."{$auction->name} é {$when}",
            sprintf('Começa %s às %s.', $when, self::hour($auction->startsAt)).($isBidder ? ' Você marcou que vai participar.' : ''),
            $auction->url,
            "{$kind->value}:{$auction->publicId}:".$to->key(),
        );
    }

    private static function local(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone(self::TIMEZONE));
    }

    private static function date(\DateTimeImmutable $at): string
    {
        return self::local($at)->format('d/m/Y');
    }

    /** "20h" ou "19h30". */
    private static function hour(\DateTimeImmutable $at): string
    {
        $local = self::local($at);

        return $local->format('i') === '00' ? $local->format('G\h') : $local->format('G\hi');
    }
}
