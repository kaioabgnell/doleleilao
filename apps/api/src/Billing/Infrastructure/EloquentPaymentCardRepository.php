<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Infrastructure;

use DoleLeilao\Billing\Application\Port\PaymentCardRepository;
use DoleLeilao\Billing\Domain\CreditCard;
use DoleLeilao\Billing\Infrastructure\Eloquent\PaymentCardRecord;

/** Sempre pelo `auctioneer_id` informado: também é lido na fila, fora de uma loja definida. */
final class EloquentPaymentCardRepository implements PaymentCardRepository
{
    public function findFor(int $auctioneerId): ?CreditCard
    {
        $record = PaymentCardRecord::withoutGlobalScope('tenant')->where('auctioneer_id', $auctioneerId)->first();

        return $record === null ? null : self::toDomain($record);
    }

    public function save(int $auctioneerId, CreditCard $card): void
    {
        PaymentCardRecord::withoutGlobalScope('tenant')->updateOrCreate(['auctioneer_id' => $auctioneerId], [
            'brand' => $card->brand,
            'last4' => $card->last4,
            'holder_name' => $card->holderName,
            'exp_month' => $card->expMonth,
            'exp_year' => $card->expYear,
        ]);
    }

    public static function toDomain(PaymentCardRecord $record): CreditCard
    {
        return new CreditCard($record->brand, $record->last4, $record->holder_name, $record->exp_month, $record->exp_year);
    }
}
