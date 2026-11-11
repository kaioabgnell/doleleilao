<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Domain;

/**
 * Situação do débito da taxa no cartão. Sem gateway (D-54), todo débito nasce e fica `pending`;
 * `paid` e `failed` passam a valer quando a cobrança for integrada.
 */
enum FeeChargeStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'A cobrar',
            self::Paid => 'Pago',
            self::Failed => 'Não autorizado',
        };
    }
}
