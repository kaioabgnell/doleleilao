<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\AuctioneerResource;
use App\Http\Resources\AuctioneerUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/v1/auctioneer/me: quem está logado e qual loja. */
final class MeController
{
    use ResolvesAuctioneerUser;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->currentUser($request)->load('auctioneer');

        return response()->json([
            'user' => new AuctioneerUserResource($user),
            'auctioneer' => new AuctioneerResource($user->auctioneer),
        ]);
    }
}
