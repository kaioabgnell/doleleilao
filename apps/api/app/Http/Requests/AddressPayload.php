<?php

declare(strict_types=1);

namespace App\Http\Requests;

use DoleLeilao\Shared\Domain\Address;
use Illuminate\Http\Request;

/**
 * Campo `address` dos formulários (loja e participante). Aqui só o formato; CEP, UF, obrigatórios
 * e tamanhos ficam no domínio (`Address`). Nulo ou CEP vazio apaga o endereço.
 */
final class AddressPayload
{
    private const array FIELDS = ['postal_code', 'street', 'number', 'complement', 'district', 'city', 'state'];

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'address' => ['sometimes', 'nullable', 'array:'.implode(',', self::FIELDS)],
            'address.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Só chame com `$request->has('address')`. Nulo = apagar o endereço.
     *
     * @return array<string, string|null>|null
     */
    public static function from(Request $request): ?array
    {
        $raw = $request->input('address');
        if (! is_array($raw)) {
            return null;
        }

        $data = [];
        foreach (self::FIELDS as $field) {
            $value = $raw[$field] ?? null;
            $data[$field] = is_string($value) ? $value : null;
        }

        return trim((string) $data['postal_code']) === '' ? null : $data;
    }

    /** @return array<string, string|null>|null */
    public static function present(?Address $address): ?array
    {
        return $address === null ? null : [
            'postal_code' => $address->postalCode->formatted(),
            'street' => $address->street,
            'number' => $address->number,
            'complement' => $address->complement,
            'district' => $address->district,
            'city' => $address->city,
            'state' => $address->state,
        ];
    }
}
