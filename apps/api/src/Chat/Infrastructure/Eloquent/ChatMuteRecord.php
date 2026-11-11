<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $auctioneer_id
 * @property int $auction_id
 * @property int $bidder_id
 * @property Carbon|null $until
 * @property int|null $muted_by
 */
final class ChatMuteRecord extends Model
{
    use BelongsToTenant;
    use MicrosecondTimestamps;

    protected $table = 'chat_mutes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['until' => 'immutable_datetime'];
    }
}
