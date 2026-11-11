<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\AuctioneerResource;
use App\Http\Resources\OnboardingResource;
use DoleLeilao\Storefront\Application\DismissOnboarding;
use DoleLeilao\Storefront\Application\GetOnboardingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Seção "Comece seu primeiro leilão" do dashboard (D-42). */
final class OnboardingController
{
    use ResolvesAuctioneerUser;

    public function show(Request $request, GetOnboardingProgress $progress): OnboardingResource
    {
        $auctioneer = $this->currentUser($request)->auctioneer;

        return new OnboardingResource(
            $progress->handle($auctioneer->id),
            AuctioneerResource::storeUrl($auctioneer->slug),
        );
    }

    public function dismiss(Request $request, DismissOnboarding $dismiss): JsonResponse
    {
        $dismiss->handle($this->currentUser($request)->auctioneer_id);

        return response()->json(null, 204);
    }
}
