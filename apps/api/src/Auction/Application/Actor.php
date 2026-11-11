<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

/** Quem fez a ação, para o log de auditoria (RF-D05). */
final readonly class Actor
{
    private function __construct(public string $type, public ?int $id) {}

    public static function auctioneerUser(int $id): self
    {
        return new self('auctioneer_user', $id);
    }

    public static function system(): self
    {
        return new self('system', null);
    }
}
