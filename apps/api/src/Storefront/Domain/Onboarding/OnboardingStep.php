<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain\Onboarding;

/**
 * Os 4 primeiros passos do leiloeiro, na ordem sugerida (D-42, D-55). O pagamento vem antes do
 * leilão porque agendar exige o cartão de crédito (D-54).
 */
enum OnboardingStep: string
{
    case Company = 'company';
    case Products = 'products';
    case Payment = 'payment';
    case Auction = 'auction';
}
