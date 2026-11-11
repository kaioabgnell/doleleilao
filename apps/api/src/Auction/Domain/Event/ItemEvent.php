<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

/** Evento de um item do lote. `sequence` ordena os eventos públicos do item (o cliente descarta os antigos). */
abstract readonly class ItemEvent
{
    public function __construct(
        public int $itemId,
        public string $itemPublicId,
        public int $auctionId,
        public int $sequence,
    ) {}
}
