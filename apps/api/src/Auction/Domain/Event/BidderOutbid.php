<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

use DoleLeilao\Shared\Domain\Money;

/** Aviso privado: "Você foi superado" (não muda a sequência pública). */
final readonly class BidderOutbid extends ItemEvent
{
    public function __construct(
        int $itemId,
        string $itemPublicId,
        int $auctionId,
        int $sequence,
        public int $bidderId,
        public Money $newPrice,
    ) {
        parent::__construct($itemId, $itemPublicId, $auctionId, $sequence);
    }
}
