<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Infrastructure;

use DoleLeilao\Chat\Application\Port\ChatThrottle;
use Illuminate\Support\Facades\RateLimiter;

/** 1 mensagem por segundo e 20 por minuto (specs/04 segurança). */
final class RateLimiterChatThrottle implements ChatThrottle
{
    public function tooMany(string $key): bool
    {
        if (RateLimiter::tooManyAttempts("{$key}:s", 1) || RateLimiter::tooManyAttempts("{$key}:m", 20)) {
            return true;
        }
        RateLimiter::hit("{$key}:s", 1);
        RateLimiter::hit("{$key}:m", 60);

        return false;
    }
}
