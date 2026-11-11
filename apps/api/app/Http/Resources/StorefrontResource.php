<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Requests\AddressPayload;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Domain\AuctioneerStatus;
use DoleLeilao\Storefront\Domain\Storefront;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Aparência e contato da loja. Chaves das listas fechadas, nunca hex (specs/05-modelo-de-dados.md).
 *
 * @mixin Storefront
 */
final class StorefrontResource extends JsonResource
{
    public function __construct(Storefront $storefront, private readonly AuctioneerRecord $auctioneer)
    {
        parent::__construct($storefront);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Storefront $s */
        $s = $this->resource;
        $theme = $s->theme();
        $settings = $this->auctioneer->storefront;

        return [
            'template' => $theme?->template,
            'primary_color' => $theme?->primary,
            'accent_color' => $theme?->accent,
            'background' => $theme?->background,
            'heading_font' => $theme?->headingFont,
            'logo' => [
                'kind' => $settings->logo_kind ?? 'none',
                'url' => app(MediaStorage::class)->url($settings?->logo_path),
            ],
            'display_name' => $this->auctioneer->display_name,
            'contact_phone' => $s->contactPhone()?->formatted(),
            'contact_email' => $s->contactEmail(),
            'socials' => (object) $s->socials(),
            // De onde a loja envia os produtos. Só no painel: a loja pública não mostra.
            'address' => AddressPayload::present($s->address()),
            'store_url' => AuctioneerResource::storeUrl($this->auctioneer->slug),
            // A loja fica pública com o e-mail validado (RF-A04) e um modelo escolhido.
            'is_public' => $this->auctioneer->status === AuctioneerStatus::Active && $theme !== null,
        ];
    }
}
