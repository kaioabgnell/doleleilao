<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Problem;
use Closure;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use DoleLeilao\Storefront\Domain\AuctioneerStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rotas do participante: token Sanctum com o escopo `bidder:{loja}`. A loja da requisição é a dele. */
final class AuthenticateBidder
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');
        if (! $user instanceof BidderUser || ! $user->tokenCan(BidderUser::abilityFor($user->auctioneer_id))) {
            return Problem::make(401, 'Entre na sua conta para continuar.');
        }
        if ($user->auctioneer->status === AuctioneerStatus::Suspended) {
            return Problem::make(403, 'Esta loja está fora do ar.');
        }

        $this->tenant->set($user->auctioneer_id);
        if ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinutes(5))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        /** @var Response */
        return $next($request);
    }
}
