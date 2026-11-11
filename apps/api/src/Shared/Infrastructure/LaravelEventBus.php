<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure;

use DoleLeilao\Shared\Application\EventBus;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelEventBus implements EventBus
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function publish(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
