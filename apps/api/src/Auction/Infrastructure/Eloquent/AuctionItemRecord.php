<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure\Eloquent;

use DoleLeilao\Auction\Domain\ItemStatus;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Item de um lote. Valores em centavos.
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int $auction_id
 * @property int|null $product_id
 * @property string $title
 * @property int $position
 * @property int $starting_price
 * @property int $increment
 * @property int|null $current_price
 * @property int|null $leading_bidder_id
 * @property int $bids_count
 * @property ItemStatus $status
 * @property Carbon|null $opened_at
 * @property Carbon|null $ends_at
 * @property int|null $paused_remaining_ms
 * @property Carbon|null $closed_at
 * @property int $sequence
 * @property-read AuctionRecord $auction
 * @property-read ProductRecord|null $product
 * @property-read BidderUser|null $leader
 */
final class AuctionItemRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    protected $table = 'auction_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
            'position' => 'integer',
            'starting_price' => 'integer',
            'increment' => 'integer',
            'current_price' => 'integer',
            'bids_count' => 'integer',
            'sequence' => 'integer',
            'paused_remaining_ms' => 'integer',
            'opened_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<AuctionRecord, $this> */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(AuctionRecord::class, 'auction_id');
    }

    /** @return BelongsTo<ProductRecord, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductRecord::class, 'product_id');
    }

    /** @return BelongsTo<BidderUser, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(BidderUser::class, 'leading_bidder_id');
    }
}
