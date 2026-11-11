<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Eloquent;

use DoleLeilao\Shared\Domain\Address;
use DoleLeilao\Shared\Domain\RuleViolations;
use Illuminate\Database\Eloquent\Model;

/** Leitura e gravação do endereço nas colunas `address_*` (lojas e participantes). */
final class AddressColumns
{
    private const array MAP = [
        'postal_code' => 'address_postal_code',
        'street' => 'address_street',
        'number' => 'address_number',
        'complement' => 'address_complement',
        'district' => 'address_district',
        'city' => 'address_city',
        'state' => 'address_state',
    ];

    /** Nulo sem CEP gravado (ou com dado antigo que não vale mais). */
    public static function read(Model $record): ?Address
    {
        $data = [];
        foreach (self::MAP as $key => $column) {
            $value = $record->getAttribute($column);
            $data[$key] = is_string($value) ? $value : null;
        }

        if ($data['postal_code'] === null) {
            return null;
        }

        try {
            return Address::fromArray($data);
        } catch (RuleViolations) {
            return null;
        }
    }

    /** @return array<string, string|null> */
    public static function write(?Address $address): array
    {
        return [
            'address_postal_code' => $address?->postalCode->value,
            'address_street' => $address?->street,
            'address_number' => $address?->number,
            'address_complement' => $address?->complement,
            'address_district' => $address?->district,
            'address_city' => $address?->city,
            'address_state' => $address?->state,
        ];
    }
}
