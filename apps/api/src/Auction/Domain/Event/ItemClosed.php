<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

use DoleLeilao\Auction\Domain\ItemStatus;
use DoleLeilao\Shared\Domain\Money;

/** Item encerrado. Com vencedor (`closed_sold`), vira arrematação (ItemArrematado). */
final readonly class ItemClosed extends ItemEvent
{
    public function __construct(
        int $itemId,
        string $itemPublicId,
        int $auctionId,
        int $sequence,
        public ItemStatus $result,
        public ?int $winnerId,
        public ?Money $amount,
        public \DateTimeImmutable $closedAt,
    ) {
        parent::__construct($itemId, $itemPublicId, $auctionId, $sequence);
    }
}
