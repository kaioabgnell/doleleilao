<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Categoria da loja (RF-B07).
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property string $name
 * @property string $slug
 * @property int $position
 * @property string|null $image_path
 */
final class CategoryRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    public const int MAX_PER_STORE = 50;

    protected $table = 'categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
