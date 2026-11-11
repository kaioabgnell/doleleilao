<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Eloquent;

/** Datas em DATETIME(6), sempre em UTC (specs/05-modelo-de-dados.md). */
trait MicrosecondTimestamps
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }
}
