<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

use DoleLeilao\Shared\Domain\Money;
use DoleLeilao\Shared\Domain\RuleViolation;

/**
 * Lance (ou máximo do automático) recusado. `minimum` traz o próximo valor aceito, para a tela
 * corrigir o botão de lance na hora (regra 4: "superado, o lance mínimo agora é X").
 */
final class BidRejected extends RuleViolation
{
    public function __construct(string $message, public readonly ?Money $minimum = null, string $field = 'amount_cents')
    {
        parent::__construct($field, $message);
    }

    public static function belowMinimum(Money $minimum, bool $tie, string $field = 'amount_cents'): self
    {
        $prefix = $tie ? 'Outro participante deu este lance antes. ' : '';

        return new self("{$prefix}O lance mínimo agora é {$minimum->format()}.", $minimum, $field);
    }
}
