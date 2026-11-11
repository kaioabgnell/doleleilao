<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Taxa de um item arrematado, com a faixa aplicada (cópia).
 *
 * @property int $id
 * @property int $auctioneer_id
 * @property int $fee_charge_id
 * @property int|null $award_id
 * @property string $item_title
 * @property int $base_amount
 * @property int $rate_bps
 * @property int $fee_amount
 */
final class AwardFeeRecord extends Model
{
    use BelongsToTenant;
    use MicrosecondTimestamps;

    protected $table = 'award_fees';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['base_amount' => 'integer', 'rate_bps' => 'integer', 'fee_amount' => 'integer'];
    }
}
