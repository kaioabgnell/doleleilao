<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure\Eloquent;

use DoleLeilao\Auction\Domain\AwardStatus;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Arrematação (ItemArrematado). O fluxo pós-leilão (status, mensagem ao vencedor) é da Fase 5.
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int $auction_item_id
 * @property int $bidder_id
 * @property int $amount
 * @property Carbon $awarded_at
 * @property AwardStatus $status
 * @property string|null $notes
 * @property-read AuctionItemRecord $item
 * @property-read BidderUser $bidder
 * @property-read Collection<int, WinnerMessageRecord> $messages
 */
final class AwardRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    protected $table = 'awards';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'awarded_at' => 'immutable_datetime', 'status' => AwardStatus::class];
    }

    /** @return BelongsTo<AuctionItemRecord, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(AuctionItemRecord::class, 'auction_item_id');
    }

    /** @return BelongsTo<BidderUser, $this> */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(BidderUser::class, 'bidder_id');
    }

    /** @return HasMany<WinnerMessageRecord, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(WinnerMessageRecord::class, 'award_id')->orderBy('id');
    }
}
