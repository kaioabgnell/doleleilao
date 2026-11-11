<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure\Eloquent;

use DoleLeilao\Auction\Domain\BidType;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lance. Só recebe inserções: anular preenche `voided_*` e nunca apaga a linha.
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int $auction_item_id
 * @property int $bidder_id
 * @property int $amount
 * @property BidType $type
 * @property Carbon $placed_at
 * @property string|null $idempotency_key
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $voided_by
 * @property-read BidderUser $bidder
 * @property-read AuctionItemRecord $item
 */
final class BidRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    public const null UPDATED_AT = null;

    protected $table = 'bids';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => BidType::class,
            'amount' => 'integer',
            'placed_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<BidderUser, $this> */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(BidderUser::class, 'bidder_id');
    }

    /** @return BelongsTo<AuctionItemRecord, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(AuctionItemRecord::class, 'auction_item_id');
    }
}
