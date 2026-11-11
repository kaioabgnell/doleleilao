<?php

declare(strict_types=1);

use DoleLeilao\Catalog\Domain\InvalidProduct;
use DoleLeilao\Catalog\Domain\ProductAttributes;
use DoleLeilao\Catalog\Domain\ProductPricing;
use DoleLeilao\Catalog\Domain\ProductStatus;
use DoleLeilao\Catalog\Domain\YouTubeVideoId;
use DoleLeilao\Shared\Domain\Money;

describe('link do YouTube (RF-C03, D-26)', function () {
    it('extrai o ID dos formatos de link mais comuns', function (string $url) {
        expect(YouTubeVideoId::fromUrl($url)->value)->toBe('dQw4w9WgXcQ');
    })->with([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42',
        'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://youtu.be/dQw4w9WgXcQ?si=abc',
        'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/live/dQw4w9WgXcQ',
        '  youtu.be/dQw4w9WgXcQ  ',
    ]);

    it('recusa links que não são de vídeo do YouTube', function (string $url) {
        YouTubeVideoId::fromUrl($url);
    })->with([
        'https://vimeo.com/123456',
        'https://www.youtube.com/channel/UC123',
        'https://www.youtube.com/watch?v=curto',
        'https://youtube.com.golpe.example/watch?v=dQw4w9WgXcQ',
        'javascript:alert(1)',
    ])->throws(InvalidProduct::class, 'Cole o link de um vídeo do YouTube.');
});

describe('ficha técnica livre (RF-C06, D-27)', function () {
    it('guarda pares nome/valor, sem espaços sobrando e sem pares vazios', function () {
        $attributes = ProductAttributes::fromArray([
            ['name' => ' Calibre ', 'value' => 'Automático '],
            ['name' => '', 'value' => ''],
            ['name' => 'Diâmetro', 'value' => '41 mm'],
        ]);

        expect($attributes->toArray())->toBe([
            ['name' => 'Calibre', 'value' => 'Automático'],
            ['name' => 'Diâmetro', 'value' => '41 mm'],
        ]);
    });

    it('recusa nome sem valor, nome repetido, textos longos e mais de 30 itens', function (array $input, string $message) {
        expect(fn () => ProductAttributes::fromArray($input))->toThrow(InvalidProduct::class, $message);
    })->with([
        [[['name' => 'Cor', 'value' => '']], 'Preencha o valor de “Cor”.'],
        [[['name' => '', 'value' => 'Azul']], 'Preencha o nome do item da ficha técnica.'],
        [[['name' => 'Cor', 'value' => 'Azul'], ['name' => 'cor', 'value' => 'Verde']], '“cor” aparece duas vezes na ficha técnica.'],
        [[['name' => str_repeat('a', 41), 'value' => 'x']], 'Use até 40 caracteres no nome'],
        [[['name' => 'x', 'value' => str_repeat('a', 121)]], 'Use até 120 caracteres no valor'],
        [array_map(fn (int $i) => ['name' => "Item {$i}", 'value' => 'y'], range(1, 31)), 'A ficha técnica pode ter até 30 itens.'],
    ]);
});

describe('valores (RF-C01, D-24)', function () {
    it('aceita valor inicial > 0, incremento ≥ R$ 1,00 e valor de referência opcional', function () {
        $pricing = new ProductPricing(Money::fromCents(150000), Money::fromCents(10000), null);

        expect($pricing->startingPrice->cents)->toBe(150000)
            ->and($pricing->increment->cents)->toBe(10000)
            ->and($pricing->referencePrice)->toBeNull();
    });

    it('recusa valor inicial zero e incremento menor que R$ 1,00', function (int $start, int $increment, string $field, string $message) {
        expect(fn () => new ProductPricing(Money::fromCents($start), Money::fromCents($increment), null))
            ->toThrow(fn (InvalidProduct $e) => expect($e->field)->toBe($field)->and($e->getMessage())->toBe($message));
    })->with([
        [0, 10000, 'starting_price_cents', 'O valor inicial do lance precisa ser maior que zero.'],
        [10000, 99, 'increment_cents', 'O incremento mínimo é R$ 1,00.'],
    ]);
});

describe('status (RF-C05, RF-A12)', function () {
    it('o leiloeiro só alterna entre rascunho e disponível', function () {
        expect(ProductStatus::Draft->canBeSetByAuctioneer())->toBeTrue()
            ->and(ProductStatus::Available->canBeSetByAuctioneer())->toBeTrue()
            ->and(ProductStatus::InAuction->canBeSetByAuctioneer())->toBeFalse()
            ->and(ProductStatus::Sold->isLocked())->toBeTrue()
            ->and(ProductStatus::Draft->isLocked())->toBeFalse();
    });

    it('produto disponível precisa de pelo menos 1 foto', function () {
        ProductStatus::Available->assertPublishable(photoCount: 0);
    })->throws(InvalidProduct::class, 'Adicione pelo menos 1 foto para deixar o produto disponível.');

    it('rascunho não precisa de foto', function () {
        ProductStatus::Draft->assertPublishable(photoCount: 0);
        expect(true)->toBeTrue();
    });
});
