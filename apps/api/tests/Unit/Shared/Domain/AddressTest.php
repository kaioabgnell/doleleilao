<?php

declare(strict_types=1);

use DoleLeilao\Shared\Domain\Address;
use DoleLeilao\Shared\Domain\InvalidAddress;
use DoleLeilao\Shared\Domain\PostalCode;
use DoleLeilao\Shared\Domain\RuleViolations;

function sampleAddress(array $overrides = []): array
{
    return [
        'postal_code' => '01310-100',
        'street' => 'Avenida Paulista',
        'number' => '1000',
        'complement' => null,
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
        ...$overrides,
    ];
}

it('aceita CEP com ou sem hífen e guarda só os números', function (string $input) {
    $cep = PostalCode::fromString($input);

    expect($cep->value)->toBe('01310100')
        ->and($cep->formatted())->toBe('01310-100');
})->with(['01310-100', '01310100', ' 01.310-100 ']);

it('recusa CEP sem 8 números', function (string $input) {
    PostalCode::fromString($input, 'cep');
})->with(['', '0131010', '013101000', '00000-000', 'abc'])
    ->throws(InvalidAddress::class, 'CEP inválido.');

it('monta o endereço limpando espaços e com a UF em maiúsculas', function () {
    $address = Address::fromArray(sampleAddress(['street' => '  Avenida   Paulista ', 'state' => 'sp', 'complement' => '  ']));

    expect($address->postalCode->value)->toBe('01310100')
        ->and($address->street)->toBe('Avenida Paulista')
        ->and($address->complement)->toBeNull()
        ->and($address->state)->toBe('SP');
});

it('aponta todos os campos que faltam de uma vez, com o prefixo do formulário', function () {
    try {
        Address::fromArray(['postal_code' => '123', 'street' => '', 'number' => ' ', 'district' => '', 'city' => '', 'state' => 'XX'], 'address');
        test()->fail('Deveria recusar o endereço.');
    } catch (RuleViolations $e) {
        expect(array_keys($e->toArray()))->toEqualCanonicalizing([
            'address.postal_code', 'address.street', 'address.number', 'address.district', 'address.city', 'address.state',
        ])->and($e->toArray()['address.number'][0])->toBe('Informe o número. Sem número? Escreva S/N.');
    }
});

it('limita o tamanho de cada campo', function () {
    try {
        Address::fromArray(sampleAddress(['number' => str_repeat('1', 11), 'complement' => str_repeat('a', 61)]));
        test()->fail('Deveria recusar o endereço.');
    } catch (RuleViolations $e) {
        expect($e->toArray())->toHaveKeys(['address.number', 'address.complement'])
            ->and($e->toArray()['address.number'][0])->toBe('Use no máximo 10 caracteres.');
    }
});
