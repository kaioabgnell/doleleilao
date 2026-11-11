<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Infrastructure\Eloquent;

use DoleLeilao\Billing\Domain\CardBrand;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cartão do leiloeiro: só bandeira, 4 últimos dígitos, nome e validade (D-54).
 *
 * @property int $id
 * @property int $auctioneer_id
 * @property CardBrand $brand
 * @property string $last4
 * @property string $holder_name
 * @property int $exp_month
 * @property int $exp_year
 * @property Carbon $updated_at
 */
final class PaymentCardRecord extends Model
{
    use BelongsToTenant;
    use MicrosecondTimestamps;

    protected $table = 'payment_cards';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['brand' => CardBrand::class, 'exp_month' => 'integer', 'exp_year' => 'integer', 'updated_at' => 'immutable_datetime'];
    }
}
