<?php

declare(strict_types=1);

use DoleLeilao\Shared\Domain\InvalidPhone;
use DoleLeilao\Shared\Domain\Phone;

it('aceita celular e fixo brasileiros com DDD, com ou sem máscara', function (string $input, string $digits, string $formatted) {
    $phone = Phone::fromString($input);

    expect($phone->value)->toBe($digits)
        ->and($phone->formatted())->toBe($formatted);
})->with([
    ['(11) 98765-4321', '11987654321', '(11) 98765-4321'],
    ['+55 21 3456-7890', '2134567890', '(21) 3456-7890'],
    ['5511987654321', '11987654321', '(11) 98765-4321'],
]);

it('recusa telefone sem DDD válido ou com tamanho errado', function (string $input) {
    Phone::fromString($input);
})->with(['98765-4321', '(01) 98765-4321', '(11) 8765-432', '(11) 88765-4321', '', 'abc'])
    ->throws(InvalidPhone::class, 'Telefone inválido.');

it('monta o link do WhatsApp com o código do Brasil', function () {
    expect(Phone::fromString('(11) 98765-4321')->whatsappNumber())->toBe('5511987654321');
});
