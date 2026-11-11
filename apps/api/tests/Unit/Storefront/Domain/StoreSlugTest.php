<?php

declare(strict_types=1);

use DoleLeilao\Storefront\Domain\InvalidSlug;
use DoleLeilao\Storefront\Domain\StoreSlug;

it('aceita letras minúsculas, números e hífen, de 3 a 40 caracteres', function (string $slug) {
    expect(StoreSlug::fromString($slug)->value)->toBe($slug);
})->with(['abc', 'leiloes-silva', 'casa-123', str_repeat('a', 40)]);

it('normaliza espaços nas pontas e maiúsculas', function () {
    expect(StoreSlug::fromString('  Leiloes-Silva ')->value)->toBe('leiloes-silva');
});

it('recusa formato inválido', function (string $slug) {
    StoreSlug::fromString($slug);
})->with(['ab', str_repeat('a', 41), 'leilões', 'casa_123', '-casa', 'casa-', 'casa--silva', 'casa silva', ''])
    ->throws(InvalidSlug::class, 'Use de 3 a 40 caracteres');

it('recusa palavras reservadas', function (string $slug) {
    StoreSlug::fromString($slug);
})->with(['painel', 'api', 'login', 'cadastro', 'admin', 'app', 'static', 'ajuda', 'termos', 'privacidade', 'blog', 'entrar'])
    ->throws(InvalidSlug::class, 'reservado');

it('sugere um slug a partir do nome da empresa', function (string $company, string $expected) {
    expect(StoreSlug::suggestFrom($company)?->value)->toBe($expected);
})->with([
    ['Leilões Silva & Filhos Ltda.', 'leiloes-silva-filhos-ltda'],
    ['  Relojoaria São João  ', 'relojoaria-sao-joao'],
    ['Ação Ótima Çaí', 'acao-otima-cai'],
    ['Empresa com um nome muito comprido que passa do limite', 'empresa-com-um-nome-muito-comprido-que'],
    ['Admin', 'admin-leiloes'],
    ['AB', 'ab-leiloes'],
]);

it('não sugere nada quando o nome não tem letras nem números', function () {
    expect(StoreSlug::suggestFrom('!!!'))->toBeNull();
});

it('gera alternativas numeradas quando a sugestão está ocupada', function () {
    expect(StoreSlug::fromString('casa-silva')->withSuffix(2)->value)->toBe('casa-silva-2')
        ->and(StoreSlug::fromString(str_repeat('a', 40))->withSuffix(12)->value)->toBe(str_repeat('a', 37).'-12');
});
