<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Requests\Auctioneer\ChangeSlugRequest;
use App\Http\Resources\AuctioneerResource;
use DoleLeilao\Storefront\Application\ChangeStoreSlug;
use Illuminate\Http\JsonResponse;

/** PUT /api/v1/auctioneer/storefront/slug (RF-A06, D-29). */
final class StorefrontSlugController
{
    use ResolvesAuctioneerUser;

    public function __invoke(ChangeSlugRequest $request, ChangeStoreSlug $change): JsonResponse
    {
        $user = $this->currentUser($request);
        $change->handle($user->auctioneer_id, $request->string('slug')->value());

        return response()->json(new AuctioneerResource($user->auctioneer->refresh()));
    }
}
