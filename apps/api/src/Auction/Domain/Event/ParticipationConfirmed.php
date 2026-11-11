<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

/** "Quero participar" (RF-E10): consumido por Notificações (participante habilitado, novo participante). */
final readonly class ParticipationConfirmed
{
    public function __construct(
        public int $auctionId,
        public int $auctioneerId,
        public int $bidderId,
        public string $participationPublicId,
    ) {}
}
