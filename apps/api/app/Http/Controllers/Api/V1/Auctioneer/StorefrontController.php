<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Requests\AddressPayload;
use App\Http\Requests\Auctioneer\UpdateStorefrontRequest;
use App\Http\Resources\StorefrontResource;
use DoleLeilao\Storefront\Application\Port\StorefrontRepository;
use DoleLeilao\Storefront\Application\UpdateStorefront;
use DoleLeilao\Storefront\Application\UpdateStorefrontInput;
use Illuminate\Http\Request;

/** Tela "Dados da empresa" (passo 1): contato, endereço de envio e aparência da loja. */
final class StorefrontController
{
    use ResolvesAuctioneerUser;

    public function show(Request $request, StorefrontRepository $storefronts): StorefrontResource
    {
        $auctioneer = $this->currentUser($request)->auctioneer->load('storefront');

        return new StorefrontResource($storefronts->get($auctioneer->id), $auctioneer);
    }

    public function update(UpdateStorefrontRequest $request, UpdateStorefront $update): StorefrontResource
    {
        $auctioneer = $this->currentUser($request)->auctioneer;
        $optional = static fn (string $key): ?string => $request->has($key) && $request->filled($key)
            ? $request->string($key)->trim()->value()
            : null;

        $cleared = [];
        foreach (['contact_phone', 'contact_email'] as $key) {
            if ($request->has($key) && ! $request->filled($key)) {
                $cleared[] = $key;
            }
        }

        $address = null;
        if ($request->has('address')) {
            $address = AddressPayload::from($request);
            if ($address === null) {
                $cleared[] = 'address';
            }
        }

        $socials = null;
        if ($request->has('socials')) {
            /** @var array<string, string|null> $raw */
            $raw = $request->validated('socials', []);
            $socials = array_filter($raw, static fn (?string $url): bool => $url !== null && $url !== '');
        }

        $storefront = $update->handle(new UpdateStorefrontInput(
            auctioneerId: $auctioneer->id,
            template: $optional('template'),
            primaryColor: $optional('primary_color'),
            accentColor: $optional('accent_color'),
            background: $optional('background'),
            headingFont: $optional('heading_font'),
            contactPhone: $optional('contact_phone'),
            contactEmail: $optional('contact_email'),
            socials: $socials,
            address: $address,
            cleared: $cleared,
        ));

        return new StorefrontResource($storefront, $auctioneer->load('storefront'));
    }
}
