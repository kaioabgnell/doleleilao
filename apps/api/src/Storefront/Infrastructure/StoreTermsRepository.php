<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure;

use DateTimeImmutable;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use DoleLeilao\Storefront\Domain\StoreTerms;
use DoleLeilao\Storefront\Infrastructure\Eloquent\StorefrontSettingsRecord;
use Illuminate\Support\Carbon;

/** Termos de uso da loja guardados em `storefront_settings` (nulo = texto padrão). */
final readonly class StoreTermsRepository
{
    public function __construct(private Clock $clock) {}

    /** @return array{terms: StoreTerms, is_default: bool, updated_at: DateTimeImmutable|null} */
    public function forAuctioneer(int $auctioneerId): array
    {
        $settings = $this->settings($auctioneerId);
        $custom = is_string($settings->terms) && $settings->terms !== '';

        return [
            'terms' => $custom ? StoreTerms::from((string) $settings->terms) : StoreTerms::default(),
            'is_default' => ! $custom,
            'updated_at' => $settings->terms_updated_at?->toDateTimeImmutable(),
        ];
    }

    /** `null` volta ao texto padrão. */
    public function save(int $auctioneerId, ?string $text): void
    {
        $terms = $text === null ? null : StoreTerms::from($text);
        $settings = $this->settings($auctioneerId);
        $settings->terms = $terms === null || $terms->text === StoreTerms::default()->text ? null : $terms->text;
        $settings->terms_updated_at = Carbon::instance($this->clock->now());
        $settings->save();

        event(new StorefrontChanged($auctioneerId));
    }

    /** O texto que o participante lê, com os campos da loja preenchidos. */
    public function rendered(int $auctioneerId, string $storeName): string
    {
        $settings = $this->settings($auctioneerId);
        $whatsapp = $settings->contact_whatsapp === null ? '' : Phone::fromString($settings->contact_whatsapp)->formatted();

        return $this->forAuctioneer($auctioneerId)['terms']->render([
            'loja' => $storeName,
            'whatsapp' => $whatsapp,
            'email' => $settings->contact_email ?? '',
        ]);
    }

    private function settings(int $auctioneerId): StorefrontSettingsRecord
    {
        return StorefrontSettingsRecord::query()->where('auctioneer_id', $auctioneerId)->firstOrFail();
    }
}
