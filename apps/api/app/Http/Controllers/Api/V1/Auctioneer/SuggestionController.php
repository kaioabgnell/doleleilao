<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use DoleLeilao\Storefront\Application\SuggestStoreIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/v1/auctioneers/suggestions?company_name= → endereço livre e nome de exibição (RF-A02, RF-A03). */
final class SuggestionController
{
    public function __invoke(Request $request, SuggestStoreIdentity $suggest): JsonResponse
    {
        $request->validate(['company_name' => ['required', 'string', 'max:120']]);
        $suggestion = $suggest->handle($request->string('company_name')->value());

        return response()->json([
            'slug' => $suggestion->slug,
            'display_name' => $suggestion->displayName,
        ]);
    }
}
