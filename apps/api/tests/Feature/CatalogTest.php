<?php

declare(strict_types=1);

use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake('public');
    Storage::fake('local');
});

describe('produtos no painel (RF-C01 a RF-C06)', function () {
    it('cria em rascunho com valores em centavos, ficha técnica e vídeo do YouTube', function () {
        $auth = liveStore();
        asAuctioneer($auth)->postJson('/api/v1/auctioneer/categories', ['name' => 'Relógios']);
        $category = asAuctioneer($auth)->getJson('/api/v1/auctioneer/categories')->json('0.id');

        asAuctioneer($auth)->postJson('/api/v1/auctioneer/products', [
            'title' => 'Rolex Datejust 1985',
            'description' => 'Caixa em aço, revisado.',
            'category_id' => $category,
            'reference_price_cents' => 4500000,
            'starting_price_cents' => 1500000,
            'increment_cents' => 50000,
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'attributes' => [['name' => 'Calibre', 'value' => 'Automático'], ['name' => '', 'value' => '']],
        ])->assertCreated()
            ->assertJsonPath('slug', 'rolex-datejust-1985')
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('category.name', 'Relógios')
            ->assertJsonPath('increment_cents', 50000)
            ->assertJsonPath('youtube_video_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('attributes', [['name' => 'Calibre', 'value' => 'Automático']]);
    });

    it('devolve todos os erros de regra juntos', function () {
        $auth = liveStore();

        asAuctioneer($auth)->postJson('/api/v1/auctioneer/products', [
            'title' => 'Relógio', 'starting_price_cents' => 0, 'increment_cents' => 50,
            'youtube_url' => 'https://vimeo.com/1', 'attributes' => [['name' => 'Cor', 'value' => '']],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.starting_price_cents.0', 'O valor inicial do lance precisa ser maior que zero.')
            ->assertJsonPath('errors.increment_cents.0', 'O incremento mínimo é R$ 1,00.')
            ->assertJsonPath('errors.youtube_url.0', 'Cole o link de um vídeo do YouTube.')
            ->assertJsonPath('errors.attributes.0', 'Preencha o valor de “Cor”.');
    });

    it('só fica disponível com foto; as fotos viram 3 tamanhos na fila (RF-C02)', function () {
        $auth = liveStore();
        $id = asAuctioneer($auth)->postJson('/api/v1/auctioneer/products', ['title' => 'Omega Seamaster', 'starting_price_cents' => 100000, 'increment_cents' => 5000])->json('id');

        asAuctioneer($auth)->patchJson("/api/v1/auctioneer/products/{$id}", ['status' => 'available'])
            ->assertUnprocessable()->assertJsonPath('errors.status.0', 'Adicione pelo menos 1 foto para deixar o produto disponível.');

        $photo = asAuctioneer($auth)->post("/api/v1/auctioneer/products/{$id}/photos", ['photos' => [UploadedFile::fake()->image('a.jpg', 2400, 1800)]], ['Accept' => 'application/json'])
            ->assertCreated()->json('0');
        expect($photo['status'])->toBe('ready')
            ->and($photo['width'])->toBe(1600)
            ->and($photo['thumb'])->toEndWith('-thumb.webp')
            ->and($photo['card']['avif'])->toEndWith('-card.avif');

        asAuctioneer($auth)->patchJson("/api/v1/auctioneer/products/{$id}", ['status' => 'available'])->assertOk()->assertJsonPath('status', 'available');
        asAuctioneer($auth)->deleteJson("/api/v1/auctioneer/products/{$id}/photos/{$photo['id']}")
            ->assertUnprocessable()->assertJsonPath('errors.photos.0', fn (string $m) => str_starts_with($m, 'Produto disponível precisa de pelo menos 1 foto'));
    });

    it('limita a 10 fotos e recusa arquivo que não é imagem', function () {
        $auth = liveStore();
        $id = asAuctioneer($auth)->postJson('/api/v1/auctioneer/products', ['title' => 'Relógio X', 'starting_price_cents' => 100, 'increment_cents' => 100])->json('id');

        asAuctioneer($auth)->post("/api/v1/auctioneer/products/{$id}/photos", ['photos' => [UploadedFile::fake()->createWithContent('x.jpg', 'não é imagem')]], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.photos.0', '“x.jpg” não é uma imagem JPG, PNG ou WebP.');
        asAuctioneer($auth)->post("/api/v1/auctioneer/products/{$id}/photos", ['photos' => array_map(fn () => UploadedFile::fake()->image('a.jpg', 50, 50), range(1, 11))], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    });

    it('sugere nomes de ficha técnica já usados pela loja', function () {
        $auth = liveStore();
        asAuctioneer($auth)->postJson('/api/v1/auctioneer/products', ['title' => 'Relógio A1', 'starting_price_cents' => 100, 'increment_cents' => 100, 'attributes' => [['name' => 'Calibre', 'value' => 'Manual'], ['name' => 'Caixa', 'value' => 'Ouro']]]);
        asAuctioneer($auth)->postJson('/api/v1/auctioneer/products', ['title' => 'Relógio A2', 'starting_price_cents' => 100, 'increment_cents' => 100, 'attributes' => [['name' => 'Calibre', 'value' => 'Automático']]]);

        asAuctioneer($auth)->getJson('/api/v1/auctioneer/products/attribute-names?q=ca')->assertExactJson(['Calibre', 'Caixa']);
    });

    it('conclui o passo 2 dos primeiros passos com 1 produto disponível (RF-A12)', function () {
        $auth = liveStore();
        asAuctioneer($auth)->getJson('/api/v1/auctioneer/onboarding')->assertJsonPath('steps.1.done', false);

        createProduct($auth);

        asAuctioneer($auth)->getJson('/api/v1/auctioneer/onboarding')
            ->assertJsonPath('steps.1.done', true)
            ->assertJsonPath('done_count', 2)
            ->assertJsonPath('steps.3.key', 'auction')
            ->assertJsonPath('steps.3.blocked_by', ['payment']); // o produto já não falta; o cartão sim (D-55)
    });

    it('produtos de uma loja não aparecem na outra', function () {
        $maria = liveStore();
        $product = createProduct($maria);
        app('auth')->forgetGuards();
        app(CurrentTenant::class)->clear();
        $joao = registerAuctioneer(['email' => 'joao@ex.com', 'document' => '12.ABC.345/01DE-35', 'slug' => 'joao-leiloes']);

        asAuctioneer($joao)->getJson('/api/v1/auctioneer/products')->assertJsonPath('total', 0);
        asAuctioneer($joao)->getJson("/api/v1/auctioneer/products/{$product['id']}")->assertNotFound();
        asAuctioneer($joao)->patchJson("/api/v1/auctioneer/products/{$product['id']}", ['title' => 'Meu'])->assertNotFound();
    });
});

describe('loja pública (RF-E01, RF-E02, RF-C04)', function () {
    it('lista só produtos públicos, filtra por categoria e busca', function () {
        $auth = liveStore();
        asAuctioneer($auth)->postJson('/api/v1/auctioneer/categories', ['name' => 'Bolso']);
        $bolso = asAuctioneer($auth)->getJson('/api/v1/auctioneer/categories')->json('0.id');
        createProduct($auth, ['title' => 'Relógio de bolso Patek', 'category_id' => $bolso]);
        createProduct($auth, ['title' => 'Rolex Submariner']);
        createProduct($auth, ['title' => 'Rascunho secreto'], available: false);
        app('auth')->forgetGuards();

        $this->withoutToken()->getJson('/api/v1/storefronts/leiloes-silva/products')->assertOk()->assertJsonPath('total', 2)
            ->assertJsonMissingPath('data.0.created_at');
        $this->getJson('/api/v1/storefronts/leiloes-silva/products?category=bolso')->assertJsonPath('total', 1)->assertJsonPath('category.name', 'Bolso');
        $this->getJson('/api/v1/storefronts/leiloes-silva/products?q=rolex')->assertJsonPath('data.0.title', 'Rolex Submariner');
        $this->getJson('/api/v1/storefronts/leiloes-silva/products/rascunho-secreto')->assertNotFound();
        $this->getJson('/api/v1/storefronts/leiloes-silva/products/rolex-submariner')->assertOk()->assertJsonPath('photos.0.status', 'ready');
        $this->getJson('/api/v1/storefronts/leiloes-silva/auctions')->assertOk()->assertJsonPath('data', []);
    });

    it('conta 1 visualização por pessoa a cada 30 minutos', function () {
        $auth = liveStore();
        $product = createProduct($auth);
        app('auth')->forgetGuards();
        $url = "/api/v1/storefronts/leiloes-silva/products/{$product['slug']}/views";

        $this->withoutToken()->postJson($url, [], ['X-Viewer-Key' => 'navegador-1'])->assertJsonPath('views', 1);
        $this->postJson($url, [], ['X-Viewer-Key' => 'navegador-1'])->assertJsonPath('views', 1);
        $this->postJson($url, [], ['X-Viewer-Key' => 'navegador-2'])->assertJsonPath('views', 2);
        $this->travel(31)->minutes();
        $this->postJson($url, [], ['X-Viewer-Key' => 'navegador-1'])->assertJsonPath('views', 3);

        expect(DB::table('product_view_daily')->sum('views'))->toEqual(3);
    });
});
