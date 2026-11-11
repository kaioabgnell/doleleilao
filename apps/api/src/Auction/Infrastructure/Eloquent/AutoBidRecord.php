<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $auctioneer_id
 * @property int $auction_item_id
 * @property int $bidder_id
 * @property int $max_amount
 * @property bool $active
 * @property Carbon $registered_at
 */
final class AutoBidRecord extends Model
{
    use BelongsToTenant;
    use MicrosecondTimestamps;

    protected $table = 'auto_bids';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'max_amount' => 'integer',
            'active' => 'boolean',
            'registered_at' => 'immutable_datetime',
        ];
    }
}
