<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure\Eloquent;

use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Quero participar" (RF-E10, D-44).
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int $auction_id
 * @property int $bidder_id
 * @property Carbon|null $enabled_notified_at
 * @property Carbon $created_at
 * @property-read AuctionRecord $auction
 * @property-read BidderUser $bidder
 */
final class ParticipationRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    protected $table = 'auction_participations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled_notified_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AuctionRecord, $this> */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(AuctionRecord::class, 'auction_id');
    }

    /** @return BelongsTo<BidderUser, $this> */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(BidderUser::class, 'bidder_id');
    }
}
