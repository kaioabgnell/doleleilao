<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use DoleLeilao\Storefront\Domain\BannerSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Banner da loja (RF-B06). A imagem do celular é obrigatória (mobile first).
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property string $title
 * @property string $image_mobile
 * @property string|null $image_desktop
 * @property string|null $link_url
 * @property int $position
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $active
 */
final class BannerRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    public const int MAX_PER_STORE = 10;

    protected $table = 'banners';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'active' => 'boolean'];
    }

    public function schedule(): BannerSchedule
    {
        return new BannerSchedule($this->starts_at?->toDateTimeImmutable(), $this->ends_at?->toDateTimeImmutable());
    }

    /**
     * Banners no ar agora, na ordem da loja.
     *
     * @param  Builder<self>  $query
     */
    public function scopeShowingAt(Builder $query, \DateTimeInterface $now): void
    {
        $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('position')->orderBy('id');
    }
}
