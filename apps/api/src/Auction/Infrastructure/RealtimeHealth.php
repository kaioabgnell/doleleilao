<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Support\Facades\Cache;

/**
 * Saúde do WebSocket (regra 9: se o Reverb cair, o pregão pausa sozinho). O relógio testa a porta do
 * Reverb a cada 2 s; falhas de envio também contam.
 */
final class RealtimeHealth
{
    private const string KEY = 'realtime:failures';

    public static function recordFailure(): void
    {
        try {
            Cache::add(self::KEY, 0, 60);
            Cache::increment(self::KEY);
        } catch (\Throwable) {
            // sem cache, o teste de porta do relógio continua valendo
        }
    }

    public static function consumeFailures(): int
    {
        try {
            $count = Scalar::int(Cache::pull(self::KEY, 0));
        } catch (\Throwable) {
            $count = 0;
        }

        return $count;
    }

    /** O servidor do Reverb aceita conexões? */
    public static function reachable(): bool
    {
        $host = config()->string('reverb.servers.reverb.hostname', '127.0.0.1');
        $host = $host === '0.0.0.0' ? '127.0.0.1' : $host;
        $port = Scalar::int(config('reverb.servers.reverb.port', 8080)); // vem do .env como texto
        $socket = @fsockopen($host, $port, $errno, $errstr, 0.5);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }
}
