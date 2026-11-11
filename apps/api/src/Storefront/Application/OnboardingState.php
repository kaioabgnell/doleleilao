<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

final readonly class OnboardingState
{
    public function __construct(
        public ?\DateTimeImmutable $completedAt,
        public ?\DateTimeImmutable $dismissedAt,
    ) {}
}
