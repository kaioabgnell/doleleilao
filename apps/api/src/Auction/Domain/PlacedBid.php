<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

use DoleLeilao\Shared\Domain\Money;

/** Lance aceito. Nunca muda depois de gravado: anular só marca `voided_*` na tabela. */
final readonly class PlacedBid
{
    public function __construct(
        public int $bidderId,
        public Money $amount,
        public BidType $type,
        public \DateTimeImmutable $placedAt,
        public ?string $idempotencyKey = null,
    ) {}
}
