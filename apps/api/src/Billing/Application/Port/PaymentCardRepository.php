<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Application\Port;

use DoleLeilao\Billing\Domain\CreditCard;

/** Um cartão por leiloeiro. Salvar de novo troca o cartão. */
interface PaymentCardRepository
{
    public function findFor(int $auctioneerId): ?CreditCard;

    public function save(int $auctioneerId, CreditCard $card): void;
}
