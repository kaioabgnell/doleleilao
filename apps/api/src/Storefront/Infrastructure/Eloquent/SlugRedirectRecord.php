<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $old_slug
 * @property int $auctioneer_id
 * @property Carbon $expires_at
 */
final class SlugRedirectRecord extends Model
{
    use MicrosecondTimestamps;

    protected $table = 'slug_redirects';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
