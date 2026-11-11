<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

use DoleLeilao\Auction\Domain\AuctionItem;

/** Estado do item depois de uma operação (resposta HTTP do lance e do automático). */
final readonly class ItemState
{
    public function __construct(
        public string $publicId,
        public string $status,
        public ?int $currentPriceCents,
        public ?int $leadingBidderId,
        public int $bidsCount,
        public int $nextMinimumCents,
        public ?\DateTimeImmutable $endsAt,
        public int $sequence,
        public ?int $autoBidMaxCents = null,
    ) {}

    public static function of(AuctionItem $item, ?int $viewerId = null): self
    {
        return new self(
            $item->publicId,
            $item->status()->value,
            $item->currentPrice()?->cents,
            $item->leadingBidderId(),
            $item->bidsCount(),
            $item->nextMinimum()->cents,
            $item->endsAt(),
            $item->sequence(),
            $viewerId === null ? null : $item->autoBidOf($viewerId)?->maxAmount->cents,
        );
    }
}
