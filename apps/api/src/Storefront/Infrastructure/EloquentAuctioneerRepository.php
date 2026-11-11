<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Domain\TaxDocument;
use DoleLeilao\Shared\Infrastructure\TaxDocumentHasher;
use DoleLeilao\Storefront\Application\NewAuctioneer;
use DoleLeilao\Storefront\Application\Port\AuctioneerRepository;
use DoleLeilao\Storefront\Application\RegisteredAuctioneer;
use DoleLeilao\Storefront\Domain\Auctioneer;
use DoleLeilao\Storefront\Domain\AuctioneerStatus;
use DoleLeilao\Storefront\Domain\DocumentAlreadyRegistered;
use DoleLeilao\Storefront\Domain\EmailAlreadyRegistered;
use DoleLeilao\Storefront\Domain\InvalidSlug;
use DoleLeilao\Storefront\Domain\StoreSlug;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use DoleLeilao\Storefront\Infrastructure\Eloquent\SlugRedirectRecord;
use DoleLeilao\Storefront\Infrastructure\Eloquent\StorefrontSettingsRecord;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class EloquentAuctioneerRepository implements AuctioneerRepository
{
    public function __construct(
        private TaxDocumentHasher $documentHasher,
        private Clock $clock,
    ) {}

    public function slugIsTaken(StoreSlug $slug, ?int $exceptAuctioneerId = null): bool
    {
        $usedByStore = AuctioneerRecord::query()
            ->where('slug', $slug->value)
            ->when($exceptAuctioneerId !== null, fn ($q) => $q->whereKeyNot($exceptAuctioneerId))
            ->exists();

        return $usedByStore || SlugRedirectRecord::query()
            ->where('old_slug', $slug->value)
            ->where('expires_at', '>', $this->clock->now())
            ->when($exceptAuctioneerId !== null, fn ($q) => $q->where('auctioneer_id', '!=', $exceptAuctioneerId))
            ->exists();
    }

    public function documentIsTaken(TaxDocument $document): bool
    {
        return AuctioneerRecord::query()->where('document_hash', $this->documentHasher->hash($document))->exists();
    }

    public function emailIsTaken(string $email): bool
    {
        return AuctioneerUser::query()->where('email', $email)->exists();
    }

    public function register(NewAuctioneer $data): RegisteredAuctioneer
    {
        try {
            return DB::transaction(function () use ($data): RegisteredAuctioneer {
                $auctioneer = AuctioneerRecord::query()->create([
                    'company_name' => $data->companyName,
                    'display_name' => $data->displayName->value,
                    'slug' => $data->slug->value,
                    'document_type' => $data->document->type,
                    'document_enc' => $data->document->value,
                    'document_hash' => $this->documentHasher->hash($data->document),
                    'status' => AuctioneerStatus::Pending,
                ]);

                $owner = AuctioneerUser::query()->create([
                    'auctioneer_id' => $auctioneer->id,
                    'name' => $data->ownerName,
                    'email' => $data->email,
                    'phone' => $data->phone->value,
                    'password' => $data->passwordHash,
                    'role' => 'owner',
                    'terms_accepted_at' => $data->registeredAt,
                ]);

                // O contato da loja já começa com o telefone e o e-mail do cadastro (editáveis no passo 1).
                StorefrontSettingsRecord::query()->create([
                    'auctioneer_id' => $auctioneer->id,
                    'contact_whatsapp' => $data->phone->value,
                    'contact_email' => $data->email,
                ]);

                return new RegisteredAuctioneer($auctioneer->id, $owner->id);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw match (true) {
                str_contains($e->getMessage(), 'auctioneers_slug_unique') => InvalidSlug::taken(),
                str_contains($e->getMessage(), 'auctioneers_document_hash_unique') => new DocumentAlreadyRegistered($data->document->type),
                str_contains($e->getMessage(), 'auctioneer_users_email_unique') => new EmailAlreadyRegistered,
                default => $e,
            };
        }
    }

    public function get(int $auctioneerId): Auctioneer
    {
        $record = AuctioneerRecord::query()->findOrFail($auctioneerId);

        return new Auctioneer(
            $record->id,
            StoreSlug::fromString($record->slug),
            $record->slug_changed_at?->toDateTimeImmutable(),
        );
    }

    public function saveSlugChange(Auctioneer $auctioneer, StoreSlug $oldSlug, \DateTimeImmutable $redirectExpiresAt): void
    {
        try {
            DB::transaction(function () use ($auctioneer, $oldSlug, $redirectExpiresAt): void {
                $now = $this->clock->now();

                // Voltar para um endereço antigo da própria loja: ele deixa de ser redirecionamento.
                SlugRedirectRecord::query()
                    ->where('old_slug', $auctioneer->slug()->value)
                    ->where(fn ($q) => $q->where('auctioneer_id', $auctioneer->id)->orWhere('expires_at', '<=', $now))
                    ->delete();
                SlugRedirectRecord::query()
                    ->where('old_slug', $oldSlug->value)
                    ->where('expires_at', '<=', $now)
                    ->delete();

                AuctioneerRecord::query()->whereKey($auctioneer->id)->update([
                    'slug' => $auctioneer->slug()->value,
                    'slug_changed_at' => $auctioneer->slugChangedAt(),
                ]);

                SlugRedirectRecord::query()->updateOrCreate(
                    ['old_slug' => $oldSlug->value],
                    ['auctioneer_id' => $auctioneer->id, 'expires_at' => $redirectExpiresAt],
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw InvalidSlug::taken();
        }
    }
}
