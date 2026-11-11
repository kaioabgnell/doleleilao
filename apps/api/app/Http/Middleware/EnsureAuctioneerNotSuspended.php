<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Problem;
use Closure;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Storefront\Domain\AuctioneerStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Leiloeiro suspenso (D-28) perde o acesso ao painel na hora. */
final class EnsureAuctioneerNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof AuctioneerUser && $user->auctioneer->status === AuctioneerStatus::Suspended) {
            return Problem::make(403, 'Esta conta está suspensa. Fale com o suporte do Dole Leilão.');
        }

        /** @var Response */
        return $next($request);
    }
}
