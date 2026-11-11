<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Log de auditoria do pregão (RF-D05). Somente inserção.
 *
 * @property int $id
 * @property int $auctioneer_id
 * @property int $auction_id
 * @property string $actor_type
 * @property int|null $actor_id
 * @property string $action
 * @property array<string, mixed> $payload
 * @property Carbon $created_at
 */
final class AuditLogRecord extends Model
{
    use BelongsToTenant;
    use MicrosecondTimestamps;

    public const null UPDATED_AT = null;

    protected $table = 'auction_audit_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
