<?php

declare(strict_types=1);

use DoleLeilao\Storefront\Domain\DisplayName;
use DoleLeilao\Storefront\Domain\InvalidDisplayName;

it('sugere as 2 primeiras palavras do nome da empresa, ignorando conectivos (D-16)', function (string $company, string $expected) {
    expect(DisplayName::suggestFrom($company)->value)->toBe($expected);
})->with([
    ['Leilões Silva & Filhos Ltda', 'Leilões Silva'],
    ['Casa de Leilões do Sul', 'Casa Leilões'],
    ['Leilões do Sul', 'Leilões Sul'],
    ['Relojoaria', 'Relojoaria'],
    ['  Dole   e   Cia ', 'Dole Cia'],
    ['Antiguidades Internacionais Brasileiras', 'Antiguidades'],
    ['Superextraordinariamentegrande Ltda', 'Superextraordinariamente'],
]);

it('aceita nome com até 2 palavras e 24 caracteres', function (string $name) {
    expect(DisplayName::fromString($name)->value)->toBe(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
})->with(['Silva', 'Leilões Silva', '  Casa   Nobre ', str_repeat('a', 24)]);

it('recusa nome vazio, com mais de 2 palavras ou mais de 24 caracteres', function (string $name, string $message) {
    expect(fn () => DisplayName::fromString($name))->toThrow(InvalidDisplayName::class, $message);
})->with([
    ['', 'Informe o nome de exibição.'],
    ['Leilões do Sul', 'no máximo 2 palavras'],
    [str_repeat('a', 25), 'no máximo 24 caracteres'],
]);
