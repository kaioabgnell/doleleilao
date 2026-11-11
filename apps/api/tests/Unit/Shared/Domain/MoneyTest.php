<?php

declare(strict_types=1);

use DoleLeilao\Shared\Domain\InvalidMoney;
use DoleLeilao\Shared\Domain\Money;

it('guarda o valor em centavos', function () {
    expect(Money::fromCents(150_000)->cents)->toBe(150_000);
});

it('converte reais com centavos sem erro de ponto flutuante', function () {
    expect(Money::fromDecimalString('1234,56')->cents)->toBe(123_456)
        ->and(Money::fromDecimalString('1.234,56')->cents)->toBe(123_456)
        ->and(Money::fromDecimalString('100')->cents)->toBe(10_000)
        ->and(Money::fromDecimalString('0,1')->cents)->toBe(10);
});

it('recusa valores negativos e textos inválidos', function (string|int $input) {
    is_int($input) ? Money::fromCents($input) : Money::fromDecimalString($input);
})->with([-1, 'abc', '1,234', ''])->throws(InvalidMoney::class);

it('soma e compara', function () {
    $atual = Money::fromCents(320_000);
    $incremento = Money::fromCents(10_000);

    expect($atual->add($incremento)->equals(Money::fromCents(330_000)))->toBeTrue()
        ->and($atual->isGreaterThanOrEqual($atual))->toBeTrue()
        ->and($atual->isGreaterThanOrEqual($atual->add($incremento)))->toBeFalse();
});

it('formata em reais (pt-BR)', function () {
    expect(Money::fromCents(1_245_000)->format())->toBe('R$ 12.450,00')
        ->and(Money::fromCents(5)->format())->toBe('R$ 0,05');
});
