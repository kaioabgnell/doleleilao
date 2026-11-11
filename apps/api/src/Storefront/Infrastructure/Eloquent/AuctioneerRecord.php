<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure\Eloquent;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Shared\Domain\TaxDocument;
use DoleLeilao\Shared\Domain\TaxDocumentType;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Storefront\Domain\AuctioneerStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $company_name
 * @property string $display_name
 * @property string $slug
 * @property Carbon|null $slug_changed_at
 * @property TaxDocumentType $document_type
 * @property string $document_enc
 * @property string $document_hash
 * @property AuctioneerStatus $status
 * @property Carbon|null $onboarding_completed_at
 * @property Carbon|null $onboarding_dismissed_at
 * @property-read StorefrontSettingsRecord|null $storefront
 * @property-read Collection<int, AuctioneerUser> $users
 */
final class AuctioneerRecord extends Model
{
    use HasPublicId;
    use MicrosecondTimestamps;

    protected $table = 'auctioneers';

    protected $guarded = ['id'];

    protected $hidden = ['document_enc', 'document_hash'];

    protected function casts(): array
    {
        return [
            'slug_changed_at' => 'datetime',
            'document_type' => TaxDocumentType::class,
            'document_enc' => 'encrypted',
            'status' => AuctioneerStatus::class,
            'onboarding_completed_at' => 'datetime',
            'onboarding_dismissed_at' => 'datetime',
        ];
    }

    public function document(): TaxDocument
    {
        return TaxDocument::of($this->document_type, $this->document_enc);
    }

    /** @return HasOne<StorefrontSettingsRecord, $this> */
    public function storefront(): HasOne
    {
        return $this->hasOne(StorefrontSettingsRecord::class, 'auctioneer_id');
    }

    /** @return HasMany<AuctioneerUser, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(AuctioneerUser::class, 'auctioneer_id');
    }
}
