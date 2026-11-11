<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/** Troca de slug: 1 vez a cada 30 dias; o endereço antigo redireciona por 90 dias (RF-A06, D-29). */
final readonly class SlugChangePolicy
{
    public const int COOLDOWN_DAYS = 30;

    public const int REDIRECT_DAYS = 90;

    public function nextChangeAllowedAt(?\DateTimeImmutable $lastChangedAt): ?\DateTimeImmutable
    {
        return $lastChangedAt?->add(new \DateInterval('P'.self::COOLDOWN_DAYS.'D'));
    }

    public function assertCanChange(?\DateTimeImmutable $lastChangedAt, \DateTimeImmutable $now): void
    {
        $allowedAt = $this->nextChangeAllowedAt($lastChangedAt);
        if ($allowedAt !== null && $now < $allowedAt) {
            throw new SlugChangeTooSoon($allowedAt);
        }
    }

    public function redirectExpiresAt(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->add(new \DateInterval('P'.self::REDIRECT_DAYS.'D'));
    }
}
