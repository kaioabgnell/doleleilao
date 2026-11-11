<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure;

/** ParticipanteBanido: Leilão bloqueia lances, Chat bloqueia mensagens, tempo real derruba o auditório. */
final readonly class BidderBanned
{
    public function __construct(public int $bidderId, public int $auctioneerId) {}
}
