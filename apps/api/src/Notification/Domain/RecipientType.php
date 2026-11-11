<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Domain;

enum RecipientType: string
{
    /** Conta do painel do leiloeiro. */
    case AuctioneerUser = 'auctioneer_user';
    /** Participante de uma loja. */
    case Bidder = 'bidder';
}
