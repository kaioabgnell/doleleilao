<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/**
 * CPF ou CNPJ validado pelo dígito verificador. Guarda só os caracteres (sem máscara).
 * O CNPJ aceita o formato alfanumérico da Receita Federal (vigente desde julho de 2026):
 * 12 caracteres [0-9A-Z] + 2 dígitos verificadores, calculados com o valor ASCII − 48.
 */
final readonly class TaxDocument
{
    private function __construct(
        public TaxDocumentType $type,
        public string $value,
    ) {}

    public static function of(TaxDocumentType $type, string $input, string $field = 'document'): self
    {
        return match ($type) {
            TaxDocumentType::Cpf => self::cpf($input, $field),
            TaxDocumentType::Cnpj => self::cnpj($input, $field),
        };
    }

    public static function cpf(string $input, string $field = 'document'): self
    {
        $digits = preg_replace('/[\s.\-]/', '', $input) ?? '';

        if (preg_match('/^\d{11}$/', $digits) !== 1 || self::allSame($digits)) {
            throw InvalidTaxDocument::of(TaxDocumentType::Cpf, $field);
        }

        foreach ([9, 10] as $length) {
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $digits[$i] * ($length + 1 - $i);
            }
            $check = ($sum * 10) % 11 % 10;
            if ($check !== (int) $digits[$length]) {
                throw InvalidTaxDocument::of(TaxDocumentType::Cpf, $field);
            }
        }

        return new self(TaxDocumentType::Cpf, $digits);
    }

    public static function cnpj(string $input, string $field = 'document'): self
    {
        $chars = strtoupper(preg_replace('/[\s.\/\-]/', '', $input) ?? '');

        if (preg_match('/^[0-9A-Z]{12}\d{2}$/', $chars) !== 1 || self::allSame($chars)) {
            throw InvalidTaxDocument::of(TaxDocumentType::Cnpj, $field);
        }

        foreach ([12, 13] as $length) {
            $sum = 0;
            $weight = $length - 7; // 5 para o 1º dígito, 6 para o 2º; volta a 9 depois do 2
            for ($i = 0; $i < $length; $i++) {
                $sum += (ord($chars[$i]) - 48) * $weight;
                $weight = $weight === 2 ? 9 : $weight - 1;
            }
            $rest = $sum % 11;
            $check = $rest < 2 ? 0 : 11 - $rest;
            if ($check !== (int) $chars[$length]) {
                throw InvalidTaxDocument::of(TaxDocumentType::Cnpj, $field);
            }
        }

        return new self(TaxDocumentType::Cnpj, $chars);
    }

    public function formatted(): string
    {
        $v = $this->value;

        return match ($this->type) {
            TaxDocumentType::Cpf => sprintf('%s.%s.%s-%s', substr($v, 0, 3), substr($v, 3, 3), substr($v, 6, 3), substr($v, 9, 2)),
            TaxDocumentType::Cnpj => sprintf('%s.%s.%s/%s-%s', substr($v, 0, 2), substr($v, 2, 3), substr($v, 5, 3), substr($v, 8, 4), substr($v, 12, 2)),
        };
    }

    /** Para a interface: o documento nunca aparece completo (RNF-09). */
    public function masked(): string
    {
        $v = $this->value;

        return match ($this->type) {
            TaxDocumentType::Cpf => sprintf('***.%s.%s-**', substr($v, 3, 3), substr($v, 6, 3)),
            TaxDocumentType::Cnpj => sprintf('%s.%s.***/****-%s', substr($v, 0, 2), substr($v, 2, 3), substr($v, 12, 2)),
        };
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->value === $other->value;
    }

    private static function allSame(string $value): bool
    {
        return count(array_unique(str_split($value))) === 1;
    }
}
