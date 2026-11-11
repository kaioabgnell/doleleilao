<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Domain;

use DoleLeilao\Shared\Domain\Money;

/**
 * Uma faixa da taxa (tabela `fee_tiers`): de `from` até `upTo` (inclusive; nulo = sem teto), com a
 * alíquota em pontos-base (750 = 7,5%), valendo de `validFrom` até antes de `validUntil`.
 */
final readonly class FeeTier
{
    public const int FULL_RATE_BPS = 10_000;

    public function __construct(
        public Money $from,
        public ?Money $upTo,
        public int $rateBps,
        public \DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validUntil = null,
    ) {
        if ($rateBps < 0 || $rateBps > self::FULL_RATE_BPS) {
            throw new \InvalidArgumentException("Alíquota fora de 0% a 100%: {$rateBps} pontos-base.");
        }
        if ($upTo !== null && $from->isGreaterThan($upTo)) {
            throw new \InvalidArgumentException('O valor máximo da faixa é menor que o mínimo.');
        }
        if ($validUntil !== null && $validUntil <= $validFrom) {
            throw new \InvalidArgumentException('O fim da vigência da faixa vem antes do início.');
        }
    }

    public function isValidAt(\DateTimeImmutable $at): bool
    {
        return $this->validFrom <= $at && ($this->validUntil === null || $at < $this->validUntil);
    }

    public function covers(Money $amount): bool
    {
        return $amount->isGreaterThanOrEqual($this->from) && ($this->upTo === null || $this->upTo->isGreaterThanOrEqual($amount));
    }

    /** Alíquota única sobre o valor inteiro, com meio centavo arredondado para cima. */
    public function feeFor(Money $amount): ItemFee
    {
        $half = intdiv(self::FULL_RATE_BPS, 2);

        return new ItemFee($amount, $this->rateBps, Money::fromCents(intdiv($amount->cents * $this->rateBps + $half, self::FULL_RATE_BPS)));
    }
}
