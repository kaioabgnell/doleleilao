<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Storefront\Application\Port\OnboardingRepository;
use DoleLeilao\Storefront\Domain\Onboarding\OnboardingCompleted;
use DoleLeilao\Storefront\Domain\Onboarding\OnboardingProgress;

/**
 * Calcula os primeiros passos a partir dos dados reais (D-42). Na primeira vez em que todos os passos
 * aparecem feitos, grava a conclusão e publica `OnboardingCompleted`.
 */
final readonly class GetOnboardingProgress
{
    public function __construct(
        private OnboardingRepository $onboarding,
        private Clock $clock,
        private EventBus $events,
    ) {}

    public function handle(int $auctioneerId): OnboardingProgress
    {
        $state = $this->onboarding->state($auctioneerId);
        $progress = OnboardingProgress::evaluate(
            $this->onboarding->facts($auctioneerId),
            $state->completedAt,
            $state->dismissedAt,
        );

        if ($progress->justCompleted()) {
            $now = $this->clock->now();
            if ($this->onboarding->markCompleted($auctioneerId, $now)) {
                $this->events->publish(new OnboardingCompleted($auctioneerId, $now));
            }
        }

        return $progress;
    }
}
