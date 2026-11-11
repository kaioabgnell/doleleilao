<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Storefront\Application\Port\OnboardingRepository;

/** Oculta a seção "Comece seu primeiro leilão" depois de concluída (RF-A16). */
final readonly class DismissOnboarding
{
    public function __construct(
        private GetOnboardingProgress $progress,
        private OnboardingRepository $onboarding,
        private Clock $clock,
    ) {}

    public function handle(int $auctioneerId): void
    {
        $this->progress->handle($auctioneerId)->assertCanDismiss();
        $this->onboarding->markDismissed($auctioneerId, $this->clock->now());
    }
}
