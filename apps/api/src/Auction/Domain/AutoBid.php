<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

use DoleLeilao\Shared\Domain\Money;

/**
 * Lance automático: o máximo que o participante autoriza (regra 6). Um por participante por item.
 * `registeredAt` desempata máximos iguais (vence quem cadastrou primeiro).
 */
final class AutoBid
{
    public function __construct(
        public readonly int $bidderId,
        public private(set) Money $maxAmount,
        public private(set) \DateTimeImmutable $registeredAt,
        public private(set) bool $active = true,
    ) {}

    public function raiseTo(Money $max, \DateTimeImmutable $now): void
    {
        $this->maxAmount = $max;
        $this->registeredAt = $now;
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /** Ordem de força: maior máximo; empate → cadastrado antes. */
    public function beats(self $other): bool
    {
        return $this->maxAmount->cents > $other->maxAmount->cents
            || ($this->maxAmount->cents === $other->maxAmount->cents && $this->registeredAt < $other->registeredAt);
    }
}
