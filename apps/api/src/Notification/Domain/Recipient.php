<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Domain;

/** Quem recebe a notificação, sempre dentro de uma loja (tenant). */
final readonly class Recipient
{
    private function __construct(
        public RecipientType $type,
        public int $id,
        public int $auctioneerId,
    ) {}

    public static function auctioneerUser(int $userId, int $auctioneerId): self
    {
        return new self(RecipientType::AuctioneerUser, $userId, $auctioneerId);
    }

    public static function bidder(int $bidderId, int $auctioneerId): self
    {
        return new self(RecipientType::Bidder, $bidderId, $auctioneerId);
    }

    public function key(): string
    {
        return $this->type->value.':'.$this->id;
    }
}
