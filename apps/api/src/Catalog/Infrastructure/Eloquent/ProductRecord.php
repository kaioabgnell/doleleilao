<?php

declare(strict_types=1);

namespace DoleLeilao\Catalog\Infrastructure\Eloquent;

use DoleLeilao\Catalog\Domain\ProductStatus;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use DoleLeilao\Storefront\Infrastructure\Eloquent\CategoryRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Produto do catálogo (RF-C01). Valores em centavos.
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int|null $category_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property int|null $reference_price
 * @property int $starting_price
 * @property int $increment
 * @property string|null $youtube_video_id
 * @property list<array{name: string, value: string}> $attributes
 * @property ProductStatus $status
 * @property int $views_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CategoryRecord|null $category
 * @property-read Collection<int, ProductPhotoRecord> $photos
 */
final class ProductRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    protected $table = 'products';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'status' => ProductStatus::class,
            'reference_price' => 'integer',
            'starting_price' => 'integer',
            'increment' => 'integer',
            'views_count' => 'integer',
        ];
    }

    /** @return BelongsTo<CategoryRecord, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryRecord::class, 'category_id');
    }

    /** @return HasMany<ProductPhotoRecord, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhotoRecord::class, 'product_id')->orderBy('position')->orderBy('id');
    }
}
