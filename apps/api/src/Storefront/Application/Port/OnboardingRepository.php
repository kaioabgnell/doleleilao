<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application\Port;

use DoleLeilao\Storefront\Application\OnboardingState;
use DoleLeilao\Storefront\Domain\Onboarding\OnboardingFacts;

interface OnboardingRepository
{
    public function facts(int $auctioneerId): OnboardingFacts;

    public function state(int $auctioneerId): OnboardingState;

    /** Grava a conclusão uma única vez. Devolve false se outra requisição já gravou. */
    public function markCompleted(int $auctioneerId, \DateTimeImmutable $at): bool;

    public function markDismissed(int $auctioneerId, \DateTimeImmutable $at): void;
}
