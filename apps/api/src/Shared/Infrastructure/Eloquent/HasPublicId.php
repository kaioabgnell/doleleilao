<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Eloquent;

use Illuminate\Support\Str;

/** `public_id` em ULID, o identificador exposto na API (o `id` numérico fica interno). */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->getAttribute('public_id'))) {
                $model->setAttribute('public_id', strtolower((string) Str::ulid()));
            }
        });
    }
}
