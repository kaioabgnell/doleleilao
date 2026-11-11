<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain\Onboarding;

/** Dados reais usados para calcular os primeiros passos (RF-A15). */
final readonly class OnboardingFacts
{
    public function __construct(
        public bool $emailVerified,
        public bool $hasContactPhone,
        public bool $templateChosen,
        public int $availableProducts,
        /** Cartão de crédito em Configurações → Pagamento (D-54, D-55). */
        public bool $hasPaymentCard,
        public int $scheduledAuctions,
    ) {}
}
