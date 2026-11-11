<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure;

/** CadastroCompletado: o participante passa a estar habilitado em todos os lotes da loja (D-23). */
final readonly class BidderBecameEligible
{
    public function __construct(public int $bidderId, public int $auctioneerId) {}
}
