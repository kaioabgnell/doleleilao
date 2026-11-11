<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Domain;

/** Configuração do chat do lote (RF-D03): ligado, quem vê, quem escreve. */
final readonly class ChatSettings
{
    private function __construct(
        public bool $enabled,
        public bool $loggedInOnly,
        public bool $eligibleToWrite,
    ) {}

    public static function from(bool $enabled, string $visibility, string $write): self
    {
        return new self($enabled, $visibility === 'logged_in', $write === 'eligible');
    }
}
