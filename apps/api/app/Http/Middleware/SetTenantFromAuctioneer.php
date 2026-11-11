<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Painel: a loja da requisição é a do usuário do token. */
final class SetTenantFromAuctioneer
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof AuctioneerUser) {
            $this->tenant->set($user->auctioneer_id);
        }

        /** @var Response */
        return $next($request);
    }
}
