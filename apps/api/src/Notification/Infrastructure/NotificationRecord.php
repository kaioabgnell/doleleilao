<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Infrastructure;

use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $recipient_type
 * @property int $recipient_id
 * @property int $auctioneer_id
 * @property string $kind
 * @property string $title
 * @property string $body
 * @property string $url
 * @property string $dedupe_key
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
final class NotificationRecord extends Model
{
    use HasPublicId;
    use MassPrunable;
    use MicrosecondTimestamps;

    /** Guarda de 90 dias (RF-N07). */
    public const int RETENTION_DAYS = 90;

    public const UPDATED_AT = null;

    protected $table = 'notifications';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
