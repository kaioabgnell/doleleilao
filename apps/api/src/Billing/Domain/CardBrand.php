<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Domain;

/** Bandeiras aceitas no cartão do leiloeiro, reconhecidas pelo início do número (BIN). */
enum CardBrand: string
{
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case Elo = 'elo';
    case Amex = 'amex';
    case Hipercard = 'hipercard';
    case Diners = 'diners';

    /**
     * Faixas da Elo, que também começam com 4, 5 e 6: são testadas antes de Visa e Mastercard.
     * O site tem a mesma lista (apps/web/src/lib/credit-card.ts), só para mostrar a bandeira.
     */
    private const string ELO = '/^(40117[89]|431274|438935|451416|457393|45763[12]|504175|506699|5067[0-6]\d|50677[0-8]|509\d{3}|627780|636297|636368'
        .'|65003[1-3]|65003[5-9]|65004\d|65005[01]|65040[5-9]|6504[1-3]\d|65048[5-9]|65049\d|6505[0-2]\d|65053[0-8]|65054[1-9]|6505[5-8]\d|65059[0-8]'
        .'|65070\d|65071[0-8]|65072[0-7]|65090[1-9]|65091\d|650920|65165[2-9]|6516[67]\d|6550[01]\d|65502[1-9]|6550[34]\d|65505[0-8])/';

    public static function detect(string $digits): ?self
    {
        return match (true) {
            preg_match(self::ELO, $digits) === 1 => self::Elo,
            preg_match('/^(606282|3841)/', $digits) === 1 => self::Hipercard,
            preg_match('/^3[47]/', $digits) === 1 => self::Amex,
            preg_match('/^3(0[0-5]|[689])/', $digits) === 1 => self::Diners,
            preg_match('/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d\d|27[01]\d|2720)/', $digits) === 1 => self::Mastercard,
            str_starts_with($digits, '4') => self::Visa,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Visa => 'Visa',
            self::Mastercard => 'Mastercard',
            self::Elo => 'Elo',
            self::Amex => 'American Express',
            self::Hipercard => 'Hipercard',
            self::Diners => 'Diners Club',
        };
    }

    /** @return list<int> quantidades de dígitos válidas */
    public function lengths(): array
    {
        return match ($this) {
            self::Amex => [15],
            self::Diners => [14, 15, 16],
            self::Visa => [13, 16, 19],
            self::Hipercard => [16, 19],
            self::Mastercard, self::Elo => [16],
        };
    }

    public function cvvLength(): int
    {
        return $this === self::Amex ? 4 : 3;
    }
}
