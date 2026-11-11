<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain\Onboarding;

/** `PrimeirosPassosConcluidos` (RF-A16): mede o tempo do cadastro até o primeiro lote agendado. */
final readonly class OnboardingCompleted
{
    public function __construct(
        public int $auctioneerId,
        public \DateTimeImmutable $completedAt,
    ) {}
}
