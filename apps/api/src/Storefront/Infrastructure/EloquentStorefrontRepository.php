<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure;

use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Domain\RuleViolation;
use DoleLeilao\Shared\Infrastructure\Eloquent\AddressColumns;
use DoleLeilao\Storefront\Application\Port\StorefrontRepository;
use DoleLeilao\Storefront\Domain\AuctioneerStatus;
use DoleLeilao\Storefront\Domain\Storefront;
use DoleLeilao\Storefront\Domain\StoreTheme;
use DoleLeilao\Storefront\Domain\ThemeCatalog;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use DoleLeilao\Storefront\Infrastructure\Eloquent\StorefrontSettingsRecord;

final readonly class EloquentStorefrontRepository implements StorefrontRepository
{
    public function __construct(private ThemeCatalog $catalog) {}

    public function get(int $auctioneerId): Storefront
    {
        $record = StorefrontSettingsRecord::query()->where('auctioneer_id', $auctioneerId)->firstOrFail();

        return new Storefront(
            $auctioneerId,
            $this->theme($record),
            $record->contact_whatsapp !== null ? Phone::fromString($record->contact_whatsapp) : null,
            $record->contact_email,
            $record->socials ?? [],
            AddressColumns::read($record),
        );
    }

    public function save(Storefront $storefront): void
    {
        $theme = $storefront->theme();

        StorefrontSettingsRecord::query()->where('auctioneer_id', $storefront->auctioneerId)->update([
            'template' => $theme?->template,
            'primary_color_key' => $theme?->primary,
            'accent_color_key' => $theme?->accent,
            'background_key' => $theme?->background,
            'heading_font_key' => $theme?->headingFont,
            'contact_whatsapp' => $storefront->contactPhone()?->value,
            'contact_email' => $storefront->contactEmail(),
            'socials' => $storefront->socials() === [] ? null : json_encode($storefront->socials(), JSON_THROW_ON_ERROR),
            ...AddressColumns::write($storefront->address()),
        ]);
    }

    public function markPublishedIfReady(int $auctioneerId, \DateTimeImmutable $at): ?string
    {
        $updated = StorefrontSettingsRecord::query()
            ->where('auctioneer_id', $auctioneerId)
            ->whereNull('published_at')
            ->whereNotNull('template')
            ->whereHas('auctioneer', fn ($q) => $q->where('status', AuctioneerStatus::Active->value))
            ->update(['published_at' => $at]);

        return $updated === 1
            ? AuctioneerRecord::query()->find($auctioneerId)?->slug
            : null;
    }

    /** Lê o tema gravado. Se o catálogo mudou e a combinação deixou de valer, usa os padrões do modelo. */
    public function theme(StorefrontSettingsRecord $record): ?StoreTheme
    {
        if ($record->template === null) {
            return null;
        }

        try {
            return StoreTheme::create(
                $record->template,
                (string) $record->primary_color_key,
                (string) $record->accent_color_key,
                (string) $record->background_key,
                (string) $record->heading_font_key,
                $this->catalog,
            );
        } catch (RuleViolation) {
            return $this->catalog->hasTemplate($record->template)
                ? StoreTheme::defaultsFor($record->template, $this->catalog)
                : null;
        }
    }
}
