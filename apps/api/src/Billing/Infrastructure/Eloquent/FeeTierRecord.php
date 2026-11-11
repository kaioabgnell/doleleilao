<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Faixa da taxa (D-47). Tabela da plataforma: vale para todas as lojas, sem `auctioneer_id`.
 *
 * @property int $id
 * @property int $min_amount
 * @property int|null $max_amount
 * @property int $rate_bps
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 */
final class FeeTierRecord extends Model
{
    use MicrosecondTimestamps;

    protected $table = 'fee_tiers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'rate_bps' => 'integer',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
        ];
    }
}
