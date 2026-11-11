<?php

declare(strict_types=1);

namespace DoleLeilao\Catalog\Domain;

use DoleLeilao\Shared\Domain\Money;
use DoleLeilao\Shared\Domain\RuleViolations;

/**
 * Valores do produto (RF-C01, D-24): valor inicial do lance (> 0), incremento livre em reais
 * (mínimo R$ 1,00) e valor de referência opcional. São copiados para o item ao entrar num lote.
 */
final readonly class ProductPricing
{
    public const int MIN_INCREMENT_CENTS = 100;

    public function __construct(
        public Money $startingPrice,
        public Money $increment,
        public ?Money $referencePrice,
    ) {
        $errors = [];
        if ($startingPrice->cents <= 0) {
            $errors[] = new InvalidProduct('starting_price_cents', 'O valor inicial do lance precisa ser maior que zero.');
        }
        if ($increment->cents < self::MIN_INCREMENT_CENTS) {
            $errors[] = new InvalidProduct('increment_cents', 'O incremento mínimo é R$ 1,00.');
        }
        if (count($errors) === 1) {
            throw $errors[0];
        }
        if ($errors !== []) {
            throw new RuleViolations($errors);
        }
    }
}
