<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Domain;

use DoleLeilao\Shared\Domain\RuleViolations;

/**
 * Cartão de crédito do leiloeiro, onde a taxa é debitada (D-54). Ainda sem gateway de pagamento:
 * o número e o código de segurança são conferidos e descartados. Ficam só a bandeira, os 4 últimos
 * dígitos, o nome impresso e a validade. Guardar o número completo ou o CVV fora de um gateway
 * certificado (PCI DSS) não é permitido.
 */
final readonly class CreditCard
{
    private const int MAX_YEARS_AHEAD = 20;

    public function __construct(
        public CardBrand $brand,
        public string $last4,
        public string $holderName,
        public int $expMonth,
        public int $expYear,
    ) {}

    /** Confere os dados digitados. Erros em vários campos saem juntos (RuleViolations). */
    public static function register(string $number, string $holderName, string $expiry, string $cvv, \DateTimeImmutable $now): self
    {
        $errors = [];

        $digits = (string) preg_replace('/[\s.\-]/', '', $number);
        $brand = null;
        if (preg_match('/^\d{13,19}$/', $digits) !== 1 || ! self::passesLuhn($digits)) {
            $errors[] = new InvalidCard('number', 'Confira o número do cartão.');
        } elseif (($brand = CardBrand::detect($digits)) === null) {
            $errors[] = new InvalidCard('number', 'Não reconhecemos a bandeira deste cartão. Aceitamos Visa, Mastercard, Elo, American Express, Hipercard e Diners.');
        } elseif (! in_array(strlen($digits), $brand->lengths(), true)) {
            $errors[] = new InvalidCard('number', 'Confira o número do cartão.');
        }

        $holder = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $holderName)));
        if (mb_strlen($holder) < 3 || mb_strlen($holder) > 60 || preg_match("/^\\p{L}[\\p{L} .'\\-]*$/u", $holder) !== 1) {
            $errors[] = new InvalidCard('holder_name', 'Digite o nome como está impresso no cartão.');
        }

        [$month, $year] = [0, 0];
        if (preg_match('#^\s*(\d{2})\s*/\s*(\d{2}|\d{4})\s*$#', $expiry, $m) !== 1 || (int) $m[1] < 1 || (int) $m[1] > 12) {
            $errors[] = new InvalidCard('expiry', 'Digite a validade no formato MM/AA.');
        } else {
            $month = (int) $m[1];
            $year = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];
            $currentYear = (int) $now->format('Y');
            if ([$year, $month] < [$currentYear, (int) $now->format('n')]) {
                $errors[] = new InvalidCard('expiry', 'Este cartão está vencido.');
            } elseif ($year > $currentYear + self::MAX_YEARS_AHEAD) {
                $errors[] = new InvalidCard('expiry', 'Confira a validade do cartão.');
            }
        }

        $cvvLength = $brand?->cvvLength() ?? 3;
        if (preg_match('/^\d{'.$cvvLength.'}$/', trim($cvv)) !== 1) {
            $errors[] = new InvalidCard('cvv', "O código de segurança tem {$cvvLength} dígitos.");
        }

        if (count($errors) === 1) {
            throw $errors[0];
        }
        if ($errors !== []) {
            throw new RuleViolations($errors);
        }

        /** @var CardBrand $brand */
        return new self($brand, substr($digits, -4), $holder, $month, $year);
    }

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;
        foreach (str_split(strrev($digits)) as $i => $char) {
            $digit = (int) $char;
            if ($i % 2 === 1) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
