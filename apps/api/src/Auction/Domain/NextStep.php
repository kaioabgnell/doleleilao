<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/** O que o relógio faz com um lote ao vivo: esperar, abrir um item ou encerrar o lote. */
final readonly class NextStep
{
    private function __construct(public string $action, public ?int $itemId = null) {}

    public static function wait(): self
    {
        return new self('wait');
    }

    public static function open(int $itemId): self
    {
        return new self('open', $itemId);
    }

    public static function finish(): self
    {
        return new self('finish');
    }
}
