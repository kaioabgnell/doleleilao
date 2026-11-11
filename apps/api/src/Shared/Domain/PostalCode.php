<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/** CEP brasileiro: 8 dígitos, guardado sem o hífen. */
final readonly class PostalCode
{
    private function __construct(public string $value) {}

    public static function fromString(string $input, string $field = 'postal_code'): self
    {
        $digits = preg_replace('/\D/', '', $input) ?? '';

        if (preg_match('/^\d{8}$/', $digits) !== 1 || $digits === '00000000') {
            throw InvalidAddress::postalCode($field);
        }

        return new self($digits);
    }

    /** 01310-100 */
    public function formatted(): string
    {
        return substr($this->value, 0, 5).'-'.substr($this->value, 5);
    }
}
