<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

enum AuctioneerStatus: string
{
    /** Cadastrado, e-mail ainda não validado: a loja não é pública (RF-A04). */
    case Pending = 'pending';
    case Active = 'active';
    /** Suspenso pela equipe Dole Leilão (comando no servidor na v1, D-28). */
    case Suspended = 'suspended';
}
