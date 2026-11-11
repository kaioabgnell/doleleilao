<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use DoleLeilao\Identity\Infrastructure\AuctioneerEmailVerifier;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Validação do e-mail do leiloeiro (RF-A04). */
final class EmailVerificationController
{
    use ResolvesAuctioneerUser;

    /**
     * Link do e-mail (assinado, 24 h). Valida e leva a pessoa de volta ao painel no site,
     * com `?email=verificado`, `?email=link-expirado` ou `?email=link-invalido`.
     */
    public function verify(Request $request, string $user, string $hash, AuctioneerEmailVerifier $verifier): RedirectResponse
    {
        $panel = rtrim(config()->string('doleleilao.frontend_url'), '/').'/painel?email=';

        if (! $request->hasValidSignature()) {
            return redirect()->away($panel.'link-expirado');
        }

        $record = AuctioneerUser::query()->where('public_id', $user)->first();
        if ($record === null || ! hash_equals(sha1($record->getEmailForVerification()), $hash)) {
            return redirect()->away($panel.'link-invalido');
        }

        $verifier->verify($record);

        return redirect()->away($panel.'verificado');
    }

    /** Reenvia o link (limite: 6 por minuto). */
    public function resend(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Seu e-mail já está confirmado.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => "Enviamos um novo link para {$user->email}."], 202);
    }
}
