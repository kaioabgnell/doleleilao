<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use DoleLeilao\Shared\Domain\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * GET /api/v1/health: usado pelo deploy e pelo monitoramento (specs/04-arquitetura.md).
 * `serverNow` também serve de referência de horário para o cronômetro do auditório.
 */
final class HealthController
{
    public function __invoke(Clock $clock): JsonResponse
    {
        $checks = [
            'database' => $this->check(static fn () => DB::select('select 1')),
            'redis' => $this->check(static fn () => Redis::connection()->ping()),
        ];
        $ok = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'serverNow' => $clock->now()->format(DATE_RFC3339_EXTENDED),
        ], $ok ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
