<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application\Port;

/** Limite de mensagens (specs/04 segurança): 1 por segundo e 20 por minuto, por pessoa e lote. */
interface ChatThrottle
{
    /** Registra a tentativa; true se passou do limite. */
    public function tooMany(string $key): bool;
}
