<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure;

/** Emite o token Sanctum do painel (escopo `auctioneer`), com a validade de `sanctum.expiration`. */
final readonly class AuctioneerTokens
{
    /** @return array{token: string, expires_at: string} */
    public function issue(AuctioneerUser $user, ?string $deviceName = null): array
    {
        $expiresAt = now()->addMinutes(config()->integer('sanctum.expiration'));
        $token = $user->createToken($deviceName ?? 'web', [AuctioneerUser::ABILITY], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601ZuluString(),
        ];
    }
}
