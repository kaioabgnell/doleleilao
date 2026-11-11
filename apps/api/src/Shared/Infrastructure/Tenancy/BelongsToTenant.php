<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de uma loja: toda consulta filtra por `auctioneer_id` da loja atual e todo registro novo
 * nasce nela. Sem loja definida, a consulta lança MissingTenant (specs/04 multi-tenant, T-04).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $query->where($query->qualifyColumn('auctioneer_id'), app(CurrentTenant::class)->id());
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('auctioneer_id') === null) {
                $model->setAttribute('auctioneer_id', app(CurrentTenant::class)->id());
            }
        });
    }
}
