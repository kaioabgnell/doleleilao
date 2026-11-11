<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure;

use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Billing\Application\Port\PaymentCardRepository;
use DoleLeilao\Catalog\Domain\ProductStatus;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Storefront\Application\OnboardingState;
use DoleLeilao\Storefront\Application\Port\OnboardingRepository;
use DoleLeilao\Storefront\Domain\Onboarding\OnboardingFacts;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use DoleLeilao\Storefront\Infrastructure\Eloquent\StorefrontSettingsRecord;

final readonly class EloquentOnboardingRepository implements OnboardingRepository
{
    public function __construct(private PaymentCardRepository $cards) {}

    public function facts(int $auctioneerId): OnboardingFacts
    {
        $settings = StorefrontSettingsRecord::query()->where('auctioneer_id', $auctioneerId)->firstOrFail();

        return new OnboardingFacts(
            emailVerified: AuctioneerUser::query()
                ->where('auctioneer_id', $auctioneerId)
                ->where('role', 'owner')
                ->whereNotNull('email_verified_at')
                ->exists(),
            hasContactPhone: $settings->contact_whatsapp !== null,
            templateChosen: $settings->template !== null,
            // Disponível ou já usado num leilão (em leilão, vendido, sem venda): o passo 2 não volta atrás
            // só porque o produto entrou num lote.
            availableProducts: ProductRecord::query()->withoutGlobalScope('tenant')
                ->where('auctioneer_id', $auctioneerId)->where('status', '!=', ProductStatus::Draft->value)->count(),
            // D-55: cartão em Configurações → Pagamento (exigido para agendar, D-54).
            hasPaymentCard: $this->cards->findFor($auctioneerId) !== null,
            // RF-A13: lote agendado (ou que já começou) com ≥ 1 item.
            scheduledAuctions: AuctionRecord::query()->withoutGlobalScope('tenant')
                ->where('auctioneer_id', $auctioneerId)
                ->whereIn('status', [AuctionStatus::Scheduled->value, AuctionStatus::Live->value, AuctionStatus::Finished->value])
                ->whereHas('items', fn ($q) => $q->withoutGlobalScope('tenant'))
                ->count(),
        );
    }

    public function state(int $auctioneerId): OnboardingState
    {
        $record = AuctioneerRecord::query()->findOrFail($auctioneerId);

        return new OnboardingState(
            $record->onboarding_completed_at?->toDateTimeImmutable(),
            $record->onboarding_dismissed_at?->toDateTimeImmutable(),
        );
    }

    public function markCompleted(int $auctioneerId, \DateTimeImmutable $at): bool
    {
        return AuctioneerRecord::query()
            ->whereKey($auctioneerId)
            ->whereNull('onboarding_completed_at')
            ->update(['onboarding_completed_at' => $at]) === 1;
    }

    public function markDismissed(int $auctioneerId, \DateTimeImmutable $at): void
    {
        AuctioneerRecord::query()
            ->whereKey($auctioneerId)
            ->whereNull('onboarding_dismissed_at')
            ->update(['onboarding_dismissed_at' => $at]);
    }
}
