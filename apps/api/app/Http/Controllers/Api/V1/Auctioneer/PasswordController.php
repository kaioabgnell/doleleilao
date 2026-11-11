<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Requests\Auctioneer\ForgotPasswordRequest;
use App\Http\Requests\Auctioneer\ResetPasswordRequest;
use DoleLeilao\Identity\Infrastructure\AuctioneerEmailVerifier;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/** Recuperação de senha do leiloeiro (RF-A05). O link vale 60 minutos. */
final class PasswordController
{
    /** Sempre responde igual, exista ou não a conta (não revela quem está cadastrado). */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker('auctioneers')->sendResetLink([
            'email' => mb_strtolower($request->string('email')->trim()->value()),
        ]);

        return response()->json([
            'message' => 'Se este e-mail estiver cadastrado, você vai receber um link para criar uma nova senha.',
        ], 202);
    }

    /** Troca a senha, encerra as sessões abertas e, de quebra, confirma o e-mail. */
    public function reset(ResetPasswordRequest $request, AuctioneerEmailVerifier $verifier): JsonResponse
    {
        $status = Password::broker('auctioneers')->reset(
            [
                'email' => mb_strtolower($request->string('email')->trim()->value()),
                'token' => $request->string('token')->value(),
                'password' => $request->string('password')->value(),
            ],
            function (AuctioneerUser $user, string $password) use ($verifier): void {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
                $verifier->verify($user);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'Este link é inválido ou expirou. Peça um novo.']);
        }

        return response()->json(['message' => 'Senha alterada. Entre com a nova senha.']);
    }
}
