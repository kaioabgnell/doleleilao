<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/**
 * Valor monetário em reais, guardado como centavos inteiros (RNF-13). Imutável.
 * Domínio puro: sem dependência de framework.
 */
final readonly class Money
{
    private function __construct(public int $cents)
    {
        if ($cents < 0) {
            throw InvalidMoney::negative($cents);
        }
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Aceita o formato digitado no Brasil: "1.234,56", "1234,56", "100", "0,1".
     */
    public static function fromDecimalString(string $value): self
    {
        $value = trim($value);
        if (preg_match('/^(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?$/', $value, $m) !== 1) {
            throw InvalidMoney::unparseable($value);
        }

        $reais = (int) str_replace('.', '', $m[1]);
        $centavos = (int) str_pad($m[2] ?? '0', 2, '0');

        return new self($reais * 100 + $centavos);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->cents >= $other->cents;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function format(): string
    {
        return 'R$ '.number_format($this->cents / 100, 2, ',', '.');
    }
}
