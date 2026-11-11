<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure;

use Carbon\CarbonImmutable;
use DoleLeilao\Shared\Domain\Clock;

/** Relógio do servidor em UTC. Usa o Carbon para os testes poderem "viajar no tempo" (`$this->travel()`). */
final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return CarbonImmutable::now('UTC')->toDateTimeImmutable();
    }
}
