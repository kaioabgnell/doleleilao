<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

use DoleLeilao\Shared\Domain\Money;

/** Aviso privado: "Seu lance automático foi superado" (regra 6). */
final readonly class AutoBidExhausted extends ItemEvent
{
    public function __construct(
        int $itemId,
        string $itemPublicId,
        int $auctionId,
        int $sequence,
        public int $bidderId,
        public Money $maxAmount,
    ) {
        parent::__construct($itemId, $itemPublicId, $auctionId, $sequence);
    }
}
