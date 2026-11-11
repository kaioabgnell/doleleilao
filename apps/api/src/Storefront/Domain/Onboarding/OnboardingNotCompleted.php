<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain\Onboarding;

use DoleLeilao\Shared\Domain\RuleViolation;

final class OnboardingNotCompleted extends RuleViolation
{
    public function __construct()
    {
        parent::__construct('onboarding', 'Conclua os primeiros passos antes de ocultar esta seção.');
    }
}
