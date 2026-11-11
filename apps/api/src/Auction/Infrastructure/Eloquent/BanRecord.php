<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Banimento de um participante na loja (a moderação no painel chega na Fase 4).
 *
 * @property int $id
 * @property int $auctioneer_id
 * @property int $bidder_id
 * @property string|null $reason
 * @property Carbon $banned_at
 * @property Carbon|null $lifted_at
 */
final class BanRecord extends Model
{
    use BelongsToTenant;
    use MicrosecondTimestamps;

    protected $table = 'bans';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['banned_at' => 'immutable_datetime', 'lifted_at' => 'immutable_datetime'];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at');
    }
}
