<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application\Port;

use DoleLeilao\Auction\Application\BidderNotAllowed;

/** Regra 1: cadastro completo e sem banimento (D-23: sem aprovação manual). */
interface BidderGate
{
    /** @throws BidderNotAllowed */
    public function assertCanBid(int $bidderId): void;
}
