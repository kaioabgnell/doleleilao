<?php

use App\Http\Middleware\AuthenticateBidder;
use App\Http\Middleware\EnsureAuctioneerNotSuspended;
use App\Http\Middleware\SetTenantFromAuctioneer;
use App\Http\Problem;
use DoleLeilao\Auction\Application\BidderNotAllowed;
use DoleLeilao\Auction\Application\ItemNotFound;
use DoleLeilao\Auction\Domain\BidRejected;
use DoleLeilao\Chat\Application\ChatNotFound;
use DoleLeilao\Chat\Application\ChatThrottled;
use DoleLeilao\Chat\Application\ChatWriteDeniedException;
use DoleLeilao\Shared\Domain\RuleViolation;
use DoleLeilao\Shared\Domain\RuleViolations;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$isApi = static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // O Next.js (BFF) roda na mesma máquina e repassa o IP do visitante (limites de requisição).
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'auctioneer.active' => EnsureAuctioneerNotSuspended::class,
            'auctioneer.tenant' => SetTenantFromAuctioneer::class,
            'bidder' => AuthenticateBidder::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isApi): void {
        $exceptions->shouldRenderJsonWhen($isApi);

        // Lance recusado: 422 com o próximo valor aceito, para a tela corrigir o botão na hora.
        $exceptions->render(fn (BidRejected $e) => Problem::make(422, $e->getMessage(), [
            'errors' => [$e->field => [$e->getMessage()]],
            'minimum_cents' => $e->minimum?->cents,
        ]));
        $exceptions->render(fn (BidderNotAllowed $e) => Problem::make(403, $e->getMessage(), ['reason' => $e->reason]));
        $exceptions->render(fn (ItemNotFound $e) => Problem::make(404, $e->getMessage()));
        $exceptions->render(fn (ChatNotFound $e) => Problem::make(404, $e->getMessage()));
        $exceptions->render(fn (ChatWriteDeniedException $e) => Problem::make(403, $e->getMessage(), ['reason' => $e->reason->value]));
        $exceptions->render(fn (ChatThrottled $e) => Problem::make(429, $e->getMessage()));

        // Regras de negócio violadas viram 422 com o erro no campo (RuleViolation::$field).
        $exceptions->render(fn (RuleViolation $e) => Problem::validation([$e->field => [$e->getMessage()]]));
        $exceptions->render(fn (RuleViolations $e) => Problem::validation($e->toArray()));

        $exceptions->render(fn (ValidationException $e, Request $request) => $isApi($request)
            ? Problem::validation($e->errors())
            : null);

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $isApi($request)
            ? Problem::make(401, 'Entre na sua conta para continuar.')
            : null);

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }
            $status = $e->getStatusCode();
            $detail = match ($status) {
                429 => 'Muitas tentativas. Aguarde um pouco e tente de novo.',
                404 => 'Não encontramos o que você procurou.',
                default => $e->getMessage() !== '' && $status < 500 ? $e->getMessage() : null,
            };

            /** @var array<string, string> $headers */
            $headers = $e->getHeaders();

            return Problem::make($status, $detail, headers: $headers);
        });
    })->create();
