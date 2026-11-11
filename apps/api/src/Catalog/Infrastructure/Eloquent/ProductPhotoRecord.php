<?php

declare(strict_types=1);

namespace DoleLeilao\Catalog\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto de produto (RF-C02). `variants`: {thumb: {webp}, card: {webp, avif}, zoom: {webp, avif}} (caminhos no disco público).
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int $product_id
 * @property string|null $original_path
 * @property array<string, array<string, string|null>>|null $variants
 * @property string $status processing|ready|failed
 * @property int $position
 * @property int|null $width
 * @property int|null $height
 */
final class ProductPhotoRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    public const int MAX_PER_PRODUCT = 10;

    protected $table = 'product_photos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['variants' => 'array', 'position' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }
}
