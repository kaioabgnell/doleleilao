<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Infrastructure\Eloquent;

use DoleLeilao\Auction\Domain\FeePayer;
use DoleLeilao\Billing\Domain\CardBrand;
use DoleLeilao\Billing\Domain\FeeChargeStatus;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Débito da taxa de um lote no cartão do leiloeiro (D-54).
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int|null $auction_id nulo se o lote for apagado depois
 * @property string $auction_name
 * @property FeePayer $fee_payer
 * @property int $amount
 * @property FeeChargeStatus $status
 * @property CardBrand|null $card_brand
 * @property string|null $card_last4
 * @property Carbon|null $charged_at
 * @property Carbon $created_at
 * @property-read Collection<int, AwardFeeRecord> $fees
 */
final class FeeChargeRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    protected $table = 'fee_charges';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fee_payer' => FeePayer::class,
            'amount' => 'integer',
            'status' => FeeChargeStatus::class,
            'card_brand' => CardBrand::class,
            'charged_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<AwardFeeRecord, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(AwardFeeRecord::class, 'fee_charge_id')->orderBy('id');
    }
}
