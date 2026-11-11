<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

final readonly class Author
{
    private function __construct(public ?int $bidderId, public ?int $auctioneerUserId) {}

    public static function bidder(int $id): self
    {
        return new self($id, null);
    }

    public static function auctioneerUser(int $id): self
    {
        return new self(null, $id);
    }

    public function isAuctioneer(): bool
    {
        return $this->auctioneerUserId !== null;
    }

    public function key(): string
    {
        return $this->isAuctioneer() ? "u{$this->auctioneerUserId}" : "b{$this->bidderId}";
    }
}
