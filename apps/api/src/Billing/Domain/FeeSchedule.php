<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Domain;

use DoleLeilao\Shared\Domain\Money;

/**
 * Todas as faixas da taxa, de todas as vigências (D-47). As faixas ficam no banco, não no código:
 * mudar a taxa é inserir faixas novas com `valid_from` e fechar as antigas com `valid_until`.
 */
final readonly class FeeSchedule
{
    /** @var list<FeeTier> */
    private array $tiers;

    /** @param list<FeeTier> $tiers */
    public function __construct(array $tiers)
    {
        usort($tiers, static fn (FeeTier $a, FeeTier $b): int => [$a->validFrom, $a->from->cents] <=> [$b->validFrom, $b->from->cents]);
        $this->tiers = $tiers;
    }

    /** Taxa do arremate pela faixa vigente na hora em que ele aconteceu. Nulo: sem taxa (período gratuito). */
    public function feeFor(Money $amount, \DateTimeImmutable $awardedAt): ?ItemFee
    {
        foreach ($this->tiers as $tier) {
            if ($tier->isValidAt($awardedAt) && $tier->covers($amount)) {
                return $tier->feeFor($amount);
            }
        }

        return null;
    }

    /**
     * Faixas para mostrar ao leiloeiro: as que valem agora ou, se nenhuma vale ainda (período
     * gratuito), as próximas a entrar em vigor.
     *
     * @return list<FeeTier>
     */
    public function inEffect(\DateTimeImmutable $now): array
    {
        $current = array_values(array_filter($this->tiers, static fn (FeeTier $t): bool => $t->isValidAt($now)));
        if ($current !== []) {
            return $current;
        }
        $upcoming = array_values(array_filter($this->tiers, static fn (FeeTier $t): bool => $t->validFrom > $now));
        if ($upcoming === []) {
            return [];
        }
        $start = $upcoming[0]->validFrom;

        return array_values(array_filter($upcoming, static fn (FeeTier $t): bool => $t->validFrom == $start));
    }

    /** Quando a cobrança começa (a primeira vigência). Nulo: nenhuma faixa cadastrada. */
    public function startsAt(): ?\DateTimeImmutable
    {
        return $this->tiers[0]->validFrom ?? null;
    }
}
