<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Storefront\Domain\Onboarding\OnboardingProgress;
use DoleLeilao\Storefront\Domain\Onboarding\OnboardingStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GET /api/v1/auctioneer/onboarding (RF-A10 a RF-A16). Os textos ficam no site.
 *
 * @mixin OnboardingProgress
 */
final class OnboardingResource extends JsonResource
{
    public function __construct(OnboardingProgress $progress, private readonly string $storeUrl)
    {
        parent::__construct($progress);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var OnboardingProgress $p */
        $p = $this->resource;
        /** @var list<string> $available */
        $available = config('doleleilao.onboarding.available_steps', []);

        return [
            'visible' => $p->isVisible(),
            'completed' => $p->isCompleted(),
            'done_count' => $p->doneCount(),
            'total' => count(OnboardingStep::cases()),
            'next' => $p->next()?->value,
            'store_url' => $this->storeUrl,
            'steps' => array_map(static fn (OnboardingStep $step): array => [
                'key' => $step->value,
                'done' => $p->isDone($step),
                // "Em breve" enquanto a tela do passo não existir (config `onboarding.available_steps`).
                'available' => in_array($step->value, $available, true),
                'blocked' => $p->isBlocked($step),
                // O que falta antes deste passo (o leilão pede produto e cartão).
                'blocked_by' => array_map(static fn (OnboardingStep $s): string => $s->value, $p->blockedBy($step)),
                'missing' => $step === OnboardingStep::Company ? $p->missingForCompany() : [],
            ], OnboardingStep::cases()),
        ];
    }
}
