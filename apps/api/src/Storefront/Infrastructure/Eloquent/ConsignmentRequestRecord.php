<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pedido "quero leiloar" (RF-E09). status: new | read | archived.
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int|null $bidder_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string $description
 * @property list<string>|null $photos
 * @property string $status
 * @property Carbon $created_at
 */
final class ConsignmentRequestRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    protected $table = 'consignment_requests';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['photos' => 'array'];
    }
}
