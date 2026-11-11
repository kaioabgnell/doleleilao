<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/**
 * Telefone brasileiro com DDD: fixo (10 dígitos) ou celular (11 dígitos, começando com 9).
 * Guarda só os dígitos, sem o +55.
 */
final readonly class Phone
{
    private function __construct(public string $value) {}

    public static function fromString(string $input, string $field = 'phone'): self
    {
        $digits = preg_replace('/\D/', '', $input) ?? '';
        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $digits = substr($digits, 2);
        }

        $valid = preg_match('/^[1-9][1-9]\d{8}$/', $digits) === 1          // fixo
            || preg_match('/^[1-9][1-9]9\d{8}$/', $digits) === 1;          // celular

        if (! $valid) {
            throw InvalidPhone::in($field);
        }

        return new self($digits);
    }

    public function formatted(): string
    {
        $local = substr($this->value, 2);
        $split = strlen($local) - 4;

        return sprintf('(%s) %s-%s', substr($this->value, 0, 2), substr($local, 0, $split), substr($local, $split));
    }

    /** Número para `https://wa.me/{numero}` (D-25). */
    public function whatsappNumber(): string
    {
        return '55'.$this->value;
    }
}
