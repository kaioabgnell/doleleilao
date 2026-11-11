<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Application;

use DoleLeilao\Auction\Domain\FeePayer;

/** O que a cobrança precisa saber de um lote encerrado. */
final readonly class SoldAuction
{
    /** @param list<SoldItem> $items */
    public function __construct(
        public int $auctionId,
        public int $auctioneerId,
        public string $name,
        public FeePayer $feePayer,
        public array $items,
    ) {}
}
