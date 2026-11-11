<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $auctioneer_id
 * @property string|null $template
 * @property string|null $primary_color_key
 * @property string|null $accent_color_key
 * @property string|null $background_key
 * @property string|null $heading_font_key
 * @property string|null $logo_path
 * @property string $logo_kind
 * @property string|null $contact_whatsapp
 * @property string|null $contact_email
 * @property array<string, string>|null $socials
 * @property Carbon|null $published_at
 * @property string|null $terms
 * @property Carbon|null $terms_updated_at
 * @property string|null $address_postal_code
 * @property string|null $address_street
 * @property string|null $address_number
 * @property string|null $address_complement
 * @property string|null $address_district
 * @property string|null $address_city
 * @property string|null $address_state
 * @property-read AuctioneerRecord $auctioneer
 */
final class StorefrontSettingsRecord extends Model
{
    use MicrosecondTimestamps;

    protected $table = 'storefront_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['socials' => 'array', 'published_at' => 'datetime', 'terms_updated_at' => 'datetime'];
    }

    /** @return BelongsTo<AuctioneerRecord, $this> */
    public function auctioneer(): BelongsTo
    {
        return $this->belongsTo(AuctioneerRecord::class, 'auctioneer_id');
    }
}
