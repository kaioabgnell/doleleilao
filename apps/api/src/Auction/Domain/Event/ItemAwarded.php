<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain\Event;

use DoleLeilao\Shared\Domain\Money;

/** ItemArrematado: consumido por Notificações (vencedor e leiloeiro), Catálogo e, na v2, Cobrança. */
final readonly class ItemAwarded
{
    public function __construct(
        public int $auctionId,
        public int $itemId,
        public string $itemPublicId,
        public string $awardPublicId,
        public int $bidderId,
        public Money $amount,
        public \DateTimeImmutable $awardedAt,
    ) {}
}
