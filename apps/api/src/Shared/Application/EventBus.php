<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Application;

/** Publica eventos de domínio para os outros contextos (fila Redis em produção). */
interface EventBus
{
    public function publish(object $event): void;
}
