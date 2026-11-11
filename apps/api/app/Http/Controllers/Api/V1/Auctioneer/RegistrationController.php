<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Requests\Auctioneer\RegisterAuctioneerRequest;
use App\Http\Resources\AuctioneerResource;
use App\Http\Resources\AuctioneerUserResource;
use DoleLeilao\Identity\Infrastructure\AuctioneerTokens;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Storefront\Application\RegisterAuctioneer;
use DoleLeilao\Storefront\Application\RegisterAuctioneerInput;
use Illuminate\Http\JsonResponse;

/** Cadastro do leiloeiro (RF-A01 a RF-A03). Já devolve o token: o painel abre logo em seguida. */
final class RegistrationController
{
    public function __invoke(
        RegisterAuctioneerRequest $request,
        RegisterAuctioneer $register,
        AuctioneerTokens $tokens,
    ): JsonResponse {
        $result = $register->handle(new RegisterAuctioneerInput(
            ownerName: $request->string('name')->value(),
            email: $request->string('email')->value(),
            phone: $request->string('phone')->value(),
            password: $request->string('password')->value(),
            documentType: $request->string('document_type')->value(),
            document: $request->string('document')->value(),
            companyName: $request->string('company_name')->value(),
            slug: $request->string('slug')->value(),
            displayName: $request->filled('display_name') ? $request->string('display_name')->value() : null,
        ));

        $user = AuctioneerUser::query()->with('auctioneer.storefront')->findOrFail($result->ownerUserId);
        $user->sendEmailVerificationNotification();

        return response()->json([
            ...$tokens->issue($user),
            'user' => new AuctioneerUserResource($user),
            'auctioneer' => new AuctioneerResource($user->auctioneer),
        ], 201);
    }
}
