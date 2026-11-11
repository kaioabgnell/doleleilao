<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

/** Aviso privado ao participante: silenciado (ou liberado, com `muted = false`) no chat do lote. */
final readonly class BidderMuted
{
    public function __construct(
        public int $bidderId,
        public string $auctionPublicId,
        public bool $muted,
        public ?\DateTimeImmutable $until,
    ) {}
}
