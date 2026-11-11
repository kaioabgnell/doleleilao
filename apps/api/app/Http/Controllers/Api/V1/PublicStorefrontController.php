<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Problem;
use App\Http\Resources\AuctioneerResource;
use App\Http\Resources\AuctionItemResource;
use App\Http\Resources\BannerResource;
use App\Http\Resources\CategoryResource;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use DoleLeilao\Storefront\Infrastructure\Eloquent\BannerRecord;
use DoleLeilao\Storefront\Infrastructure\Eloquent\CategoryRecord;
use DoleLeilao\Storefront\Infrastructure\EloquentStorefrontRepository;
use DoleLeilao\Storefront\Infrastructure\StorefrontResolver;
use DoleLeilao\Storefront\Infrastructure\StoreTermsRepository;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/storefronts/{slug}: o que a loja pública precisa (RF-E01): tema, logo, contato,
 * banners no ar e categorias. Endereço antigo (RF-A06) → 301 com o novo slug. Loja fora do ar → 404.
 */
final class PublicStorefrontController
{
    /**
     * GET /api/v1/storefronts/{slug}/status: consulta leve usada pelo site antes de montar a página,
     * para responder com 301 (endereço antigo) ou 404 de verdade.
     */
    public function status(string $slug, StorefrontResolver $resolver): JsonResponse
    {
        $resolved = $resolver->resolve($slug);

        return response()->json([
            'state' => $resolved['state'],
            'slug' => match ($resolved['state']) {
                'live' => $resolved['auctioneer']->slug,
                'moved' => $resolved['slug'],
                'missing' => null,
            },
        ]);
    }

    /** GET /api/v1/storefronts/{slug}/terms: termos de uso da loja, com os dados da loja preenchidos. */
    public function terms(string $slug, StorefrontResolver $resolver, StoreTermsRepository $terms): JsonResponse
    {
        $resolved = $resolver->resolve($slug);
        if ($resolved['state'] === 'moved') {
            return response()->json(['redirect_to' => $resolved['slug']], 301, ['Location' => "/api/v1/storefronts/{$resolved['slug']}/terms"]);
        }
        if ($resolved['state'] === 'missing') {
            return Problem::make(404, 'Não encontramos esta loja.');
        }
        $auctioneer = $resolved['auctioneer'];

        return response()->json([
            'store' => $auctioneer->display_name,
            'terms' => $terms->rendered($auctioneer->id, $auctioneer->display_name),
            'updated_at' => AuctionItemResource::iso($terms->forAuctioneer($auctioneer->id)['updated_at']),
        ]);
    }

    public function show(
        string $slug,
        StorefrontResolver $resolver,
        CurrentTenant $tenant,
        Clock $clock,
        MediaStorage $media,
        EloquentStorefrontRepository $storefronts,
    ): JsonResponse {
        $resolved = $resolver->resolve($slug);
        if ($resolved['state'] === 'moved') {
            return response()->json(['redirect_to' => $resolved['slug']], 301, ['Location' => "/api/v1/storefronts/{$resolved['slug']}"]);
        }
        if ($resolved['state'] === 'missing') {
            return Problem::make(404, 'Não encontramos esta loja.');
        }

        $auctioneer = $resolved['auctioneer'];
        $settings = $auctioneer->storefront;
        $theme = $settings === null ? null : $storefronts->theme($settings);
        if ($settings === null || $theme === null) {
            return Problem::make(404, 'Não encontramos esta loja.');
        }

        $tenant->set($auctioneer->id);
        $phone = $settings->contact_whatsapp !== null ? Phone::fromString($settings->contact_whatsapp) : null;

        return response()->json([
            'id' => $auctioneer->public_id,
            'slug' => $auctioneer->slug,
            'display_name' => $auctioneer->display_name,
            'store_url' => AuctioneerResource::storeUrl($auctioneer->slug),
            'logo' => ['kind' => $settings->logo_kind, 'url' => $media->url($settings->logo_path)],
            'theme' => [
                'template' => $theme->template,
                'primary_color' => $theme->primary,
                'accent_color' => $theme->accent,
                'background' => $theme->background,
                'heading_font' => $theme->headingFont,
            ],
            'contact' => [
                'whatsapp' => $phone?->formatted(),
                'whatsapp_url' => $phone === null ? null : 'https://wa.me/'.$phone->whatsappNumber(),
                'email' => $settings->contact_email,
                'socials' => (object) ($settings->socials ?? []),
            ],
            'banners' => BannerResource::collection(BannerRecord::query()->showingAt($clock->now())->get()),
            'categories' => CategoryResource::collection(CategoryRecord::query()->orderBy('position')->orderBy('id')->get()),
        ]);
    }
}
