<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Application;

use DoleLeilao\Billing\Application\Port\PaymentCardRepository;
use DoleLeilao\Billing\Domain\CreditCard;
use DoleLeilao\Shared\Domain\Clock;

/** Configurações → Pagamento: confere o cartão digitado e guarda (ou troca) o do leiloeiro. */
final readonly class SavePaymentCard
{
    public function __construct(private PaymentCardRepository $cards, private Clock $clock) {}

    public function handle(int $auctioneerId, string $number, string $holderName, string $expiry, string $cvv): CreditCard
    {
        $card = CreditCard::register($number, $holderName, $expiry, $cvv, $this->clock->now());
        $this->cards->save($auctioneerId, $card);

        return $card;
    }
}
