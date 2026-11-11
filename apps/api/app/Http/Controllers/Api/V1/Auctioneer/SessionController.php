<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Problem;
use App\Http\Requests\Auctioneer\LoginRequest;
use App\Http\Resources\AuctioneerResource;
use App\Http\Resources\AuctioneerUserResource;
use DoleLeilao\Identity\Infrastructure\AuctioneerTokens;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Storefront\Domain\AuctioneerStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Login e logout do leiloeiro (RF-A05). */
final class SessionController
{
    use ResolvesAuctioneerUser;

    public function store(LoginRequest $request, AuctioneerTokens $tokens): JsonResponse
    {
        $email = mb_strtolower($request->string('email')->trim()->value());
        $password = $request->string('password')->value();
        $user = AuctioneerUser::query()->with('auctioneer.storefront')->where('email', $email)->first();

        if ($user === null) {
            Hash::make($password); // mesmo custo de quando o e-mail existe (não revela quem tem conta)
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }
        if ($user->auctioneer->status === AuctioneerStatus::Suspended) {
            return Problem::make(403, 'Esta conta está suspensa. Fale com o suporte do Dole Leilão.');
        }
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        return response()->json([
            ...$tokens->issue($user, $request->filled('device_name') ? $request->string('device_name')->value() : null),
            'user' => new AuctioneerUserResource($user),
            'auctioneer' => new AuctioneerResource($user->auctioneer),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->currentUser($request)->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
