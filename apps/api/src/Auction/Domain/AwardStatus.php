<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/** Acompanhamento da arrematação (RF-E08). O leiloeiro muda livremente (corrige engano); "final" sai da lista de pendentes. */
enum AwardStatus: string
{
    case AwaitingContact = 'awaiting_contact';
    case Negotiating = 'negotiating';
    case Paid = 'paid';
    case Delivered = 'delivered';
    case Withdrawn = 'withdrawn';

    public static function initial(): self
    {
        return self::AwaitingContact;
    }

    public function label(): string
    {
        return match ($this) {
            self::AwaitingContact => 'Aguardando contato',
            self::Negotiating => 'Em negociação',
            self::Paid => 'Pago',
            self::Delivered => 'Entregue',
            self::Withdrawn => 'Desistência',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Delivered || $this === self::Withdrawn;
    }
}
