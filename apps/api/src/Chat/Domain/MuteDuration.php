<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Domain;

/** Silenciar no leilão (RF-F08): 5, 15 ou 60 minutos, ou até o fim do leilão. */
final readonly class MuteDuration
{
    public const array OPTIONS = [5, 15, 60];

    private function __construct(public ?int $minutes) {}

    public static function minutes(int $minutes): self
    {
        if (! in_array($minutes, self::OPTIONS, true)) {
            throw new InvalidMessage('minutes', 'Escolha 5, 15 ou 60 minutos, ou até o fim do leilão.');
        }

        return new self($minutes);
    }

    public static function wholeAuction(): self
    {
        return new self(null);
    }

    /** Fim do silêncio; null = até o fim do leilão. */
    public function until(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        return $this->minutes === null ? null : $now->modify("+{$this->minutes} minutes");
    }
}
