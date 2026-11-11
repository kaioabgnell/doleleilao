<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\AuctioneerResource;
use DoleLeilao\Storefront\Application\CheckSlugAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/v1/auctioneers/slug-availability?slug= (checagem ao vivo, RF-A02). */
final class SlugAvailabilityController
{
    use ResolvesAuctioneerUser;

    public function __invoke(Request $request, CheckSlugAvailability $check): JsonResponse
    {
        $request->validate(['slug' => ['required', 'string', 'max:60']]);

        // Logado, a checagem ignora os endereços da própria loja (troca de slug).
        $auctioneerId = $request->user('sanctum') !== null ? $this->currentUser($request)->auctioneer_id : null;
        $result = $check->handle($request->string('slug')->value(), $auctioneerId);

        return response()->json([
            'slug' => $result->slug,
            'available' => $result->available,
            'reason' => $result->reason,
            'message' => $result->message,
            'store_url' => AuctioneerResource::storeUrl($result->slug),
        ]);
    }
}
