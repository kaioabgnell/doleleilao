<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Infrastructure;

use DoleLeilao\Billing\Application\Port\FeeTierRepository;
use DoleLeilao\Billing\Domain\FeeSchedule;
use DoleLeilao\Billing\Domain\FeeTier;
use DoleLeilao\Billing\Infrastructure\Eloquent\FeeTierRecord;
use DoleLeilao\Shared\Domain\Money;

final class EloquentFeeTierRepository implements FeeTierRepository
{
    public function schedule(): FeeSchedule
    {
        return new FeeSchedule(array_values(FeeTierRecord::query()->orderBy('valid_from')->orderBy('min_amount')->get()
            ->map(static fn (FeeTierRecord $r): FeeTier => new FeeTier(
                Money::fromCents($r->min_amount),
                $r->max_amount === null ? null : Money::fromCents($r->max_amount),
                $r->rate_bps,
                $r->valid_from->toDateTimeImmutable(),
                $r->valid_until?->toDateTimeImmutable(),
            ))->all()));
    }
}
