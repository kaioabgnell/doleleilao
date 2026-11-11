<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/**
 * Endereço brasileiro para envio de produtos: CEP, rua, número, complemento (opcional), bairro,
 * cidade e UF. Os erros saem por campo (`{prefixo}.street`, `{prefixo}.state`…), todos juntos.
 */
final readonly class Address
{
    public const array STATES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    private function __construct(
        public PostalCode $postalCode,
        public string $street,
        public string $number,
        public ?string $complement,
        public string $district,
        public string $city,
        public string $state,
    ) {}

    /**
     * @param  array<string, string|null>  $data  postal_code, street, number, complement, district, city, state
     *
     * @throws RuleViolations
     */
    public static function fromArray(array $data, string $field = 'address'): self
    {
        $errors = new ViolationCollector;
        $text = static fn (string $key, int $max, ?string $missing): string => self::text($data, $key, $field, $max, $missing, $errors);

        $postalCode = $errors->attempt(fn () => PostalCode::fromString($data['postal_code'] ?? '', "{$field}.postal_code"));
        $street = $text('street', 120, 'Informe o endereço (rua, avenida…).');
        $number = $text('number', 10, 'Informe o número. Sem número? Escreva S/N.');
        $complement = $text('complement', 60, null);
        $district = $text('district', 60, 'Informe o bairro.');
        $city = $text('city', 60, 'Informe a cidade.');

        $state = mb_strtoupper(trim($data['state'] ?? ''));
        if (! in_array($state, self::STATES, true)) {
            $errors->add(InvalidAddress::state("{$field}.state"));
        }

        $errors->throwIfAny();
        assert($postalCode !== null);

        return new self($postalCode, $street, $number, $complement === '' ? null : $complement, $district, $city, $state);
    }

    /**
     * Texto sem espaços repetidos. Vazio (quando obrigatório) ou longo demais vira erro no campo.
     *
     * @param  array<string, string|null>  $data
     */
    private static function text(array $data, string $key, string $field, int $max, ?string $missing, ViolationCollector $errors): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $data[$key] ?? ''));

        if ($value === '' && $missing !== null) {
            $errors->add(InvalidAddress::missing("{$field}.{$key}", $missing));
        } elseif (mb_strlen($value) > $max) {
            $errors->add(InvalidAddress::tooLong("{$field}.{$key}", $max));
        }

        return $value;
    }
}
