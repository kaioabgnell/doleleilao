<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Domain;

/** Quem está diante do chat: visitante, participante (com a situação dele) ou o leiloeiro. */
final readonly class Participant
{
    private function __construct(
        public bool $loggedIn,
        public bool $isAuctioneer,
        public bool $eligible,
        public bool $banned,
        public ?\DateTimeImmutable $mutedUntil,
        public bool $mutedForAuction,
    ) {}

    public static function visitor(): self
    {
        return new self(false, false, false, false, null, false);
    }

    public static function bidder(bool $eligible = true, bool $banned = false, ?\DateTimeImmutable $mutedUntil = null, bool $mutedForAuction = false): self
    {
        return new self(true, false, $eligible, $banned, $mutedUntil, $mutedForAuction);
    }

    public static function auctioneer(): self
    {
        return new self(true, true, true, false, null, false);
    }

    public function isMuted(\DateTimeImmutable $now): bool
    {
        return $this->mutedForAuction || ($this->mutedUntil !== null && $now < $this->mutedUntil);
    }
}
