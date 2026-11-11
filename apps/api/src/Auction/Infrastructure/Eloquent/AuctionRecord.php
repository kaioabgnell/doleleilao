<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure\Eloquent;

use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lote de leilão (D-22).
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $cover_path capa enviada pelo leiloeiro (sem ela, vale a 1ª foto do 1º item)
 * @property string|null $cover_thumb_path
 * @property Carbon $starts_at
 * @property AuctionStatus $status
 * @property array<string, mixed> $settings
 * @property int|null $current_item_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property bool $visible_in_store
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, AuctionItemRecord> $items
 * @property-read Collection<int, ParticipationRecord> $participations
 */
final class AuctionRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    protected $table = 'auctions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => AuctionStatus::class,
            'settings' => 'array',
            'starts_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'visible_in_store' => 'boolean',
        ];
    }

    /** @return HasMany<AuctionItemRecord, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AuctionItemRecord::class, 'auction_id')->orderBy('position');
    }

    /** @return HasMany<ParticipationRecord, $this> */
    public function participations(): HasMany
    {
        return $this->hasMany(ParticipationRecord::class, 'auction_id');
    }
}
