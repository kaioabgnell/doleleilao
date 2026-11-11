<?php

declare(strict_types=1);

use DoleLeilao\Storefront\Domain\InvalidStoreTheme;
use DoleLeilao\Storefront\Domain\StoreTheme;
use DoleLeilao\Storefront\Domain\ThemeCatalog;

beforeEach(function () {
    // Recorte do catálogo gerado pelo design-tokens (resources/design/theme-catalog.json).
    $this->catalog = ThemeCatalog::fromArray([
        'colors' => ['copper', 'black', 'blue', 'navy', 'dark-gold', 'wine', 'graphite'],
        'fonts' => ['inter', 'playfair', 'dm-sans'],
        'backgrounds' => [
            'white' => ['dark' => false, 'allowedColors' => ['copper', 'black', 'blue', 'navy', 'dark-gold', 'wine', 'graphite']],
            'ivory' => ['dark' => false, 'allowedColors' => ['copper', 'black', 'blue', 'navy', 'dark-gold', 'wine', 'graphite']],
            'ebony' => ['dark' => true, 'allowedColors' => ['copper', 'dark-gold']],
            'ice' => ['dark' => false, 'allowedColors' => ['copper', 'black', 'blue', 'navy', 'dark-gold', 'wine', 'graphite']],
        ],
        'templates' => [
            'watches' => ['backgrounds' => ['white', 'ivory', 'ebony'], 'defaults' => ['primary' => 'copper', 'accent' => 'dark-gold', 'background' => 'ivory', 'headingFont' => 'playfair']],
            'electronics' => ['backgrounds' => ['white', 'ice'], 'defaults' => ['primary' => 'blue', 'accent' => 'navy', 'background' => 'ice', 'headingFont' => 'inter']],
        ],
    ]);
});

it('usa os padrões do modelo', function () {
    $theme = StoreTheme::defaultsFor('watches', $this->catalog);

    expect($theme->toArray())->toBe([
        'template' => 'watches', 'primary' => 'copper', 'accent' => 'dark-gold', 'background' => 'ivory', 'headingFont' => 'playfair',
    ]);
});

it('recusa modelo desconhecido', function () {
    StoreTheme::defaultsFor('cars', $this->catalog);
})->throws(InvalidStoreTheme::class, 'Modelo de loja inválido.');

it('aceita uma combinação permitida', function () {
    $theme = StoreTheme::create('watches', 'dark-gold', 'copper', 'ebony', 'inter', $this->catalog);

    expect($theme->background)->toBe('ebony');
});

it('recusa fundo que não pertence ao modelo', function () {
    StoreTheme::create('electronics', 'blue', 'navy', 'ebony', 'inter', $this->catalog);
})->throws(InvalidStoreTheme::class, 'não está disponível neste modelo');

it('recusa cor sem contraste com o fundo (D-13)', function () {
    expect(fn () => StoreTheme::create('watches', 'black', 'copper', 'ebony', 'inter', $this->catalog))
        ->toThrow(fn (InvalidStoreTheme $e) => expect($e->field)->toBe('primary_color'));
});

it('recusa cor ou fonte fora das listas fechadas', function (string $primary, string $font, string $field) {
    expect(fn () => StoreTheme::create('watches', $primary, 'copper', 'white', $font, $this->catalog))
        ->toThrow(fn (InvalidStoreTheme $e) => expect($e->field)->toBe($field));
})->with([
    ['#ff0000', 'inter', 'primary_color'],
    ['copper', 'comic-sans', 'heading_font'],
]);

it('ao trocar de modelo mantém o que continua permitido (RF-B01)', function () {
    $theme = StoreTheme::create('watches', 'wine', 'black', 'white', 'dm-sans', $this->catalog)
        ->switchTemplate('electronics', $this->catalog);

    expect($theme->toArray())->toBe([
        'template' => 'electronics', 'primary' => 'wine', 'accent' => 'black', 'background' => 'white', 'headingFont' => 'dm-sans',
    ]);
});

it('ao trocar de modelo usa o fundo padrão quando o atual não existe no novo modelo', function () {
    $theme = StoreTheme::create('watches', 'copper', 'dark-gold', 'ebony', 'playfair', $this->catalog)
        ->switchTemplate('electronics', $this->catalog);

    expect($theme->background)->toBe('ice')
        ->and($theme->primary)->toBe('copper')
        ->and($theme->accent)->toBe('dark-gold');
});

it('ao trocar para um fundo escuro troca as cores que perderiam contraste pelos padrões do modelo', function () {
    $theme = StoreTheme::create('watches', 'black', 'copper', 'white', 'inter', $this->catalog)
        ->with(background: 'ebony', catalog: $this->catalog, adjustColors: true);

    expect($theme->primary)->toBe('copper')
        ->and($theme->accent)->toBe('copper');
});
