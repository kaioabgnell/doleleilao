<?php

declare(strict_types=1);

use DoleLeilao\Identity\Infrastructure\AuctioneerEmailVerifier;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use DoleLeilao\Shared\Infrastructure\Tenancy\MissingTenant;
use DoleLeilao\Storefront\Infrastructure\Eloquent\BannerRecord;
use DoleLeilao\Storefront\Infrastructure\Eloquent\CategoryRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake('public');
});

describe('logo (RF-B02)', function () {
    it('envia uma imagem e guarda em WebP', function () {
        $auth = registerAuctioneer();

        $response = asAuctioneer($auth)->post('/api/v1/auctioneer/storefront/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 1600, 600),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('kind', 'webp');

        $path = str_replace('/storage/', '', (string) parse_url((string) $response->json('url'), PHP_URL_PATH));
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path)) ?: [0, 0];
        expect($w)->toBe(800)->and($h)->toBe(300);

        asAuctioneer($auth)->getJson('/api/v1/auctioneer/storefront')->assertJsonPath('logo.kind', 'webp');
    });

    it('limpa scripts e eventos de um SVG', function () {
        $auth = registerAuctioneer();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><script>alert(1)</script><rect width="10" height="10" onclick="alert(2)"/><image href="https://evil.example/x.png"/></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg);

        $url = asAuctioneer($auth)->post('/api/v1/auctioneer/storefront/logo', ['logo' => $file], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('kind', 'svg')->json('url');

        $stored = Storage::disk('public')->get(str_replace('/storage/', '', (string) parse_url((string) $url, PHP_URL_PATH)));
        expect($stored)->toContain('<rect')
            ->not->toContain('script')
            ->not->toContain('onclick')
            ->not->toContain('evil.example');
    });

    it('recusa arquivo que não é imagem, mesmo com extensão de imagem', function () {
        $auth = registerAuctioneer();
        $file = UploadedFile::fake()->createWithContent('logo.png', '<?php echo "oi";');

        asAuctioneer($auth)->post('/api/v1/auctioneer/storefront/logo', ['logo' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.logo.0', 'Envie uma imagem JPG, PNG ou WebP.');
    });

    it('remove a logo e volta para a logo de texto', function () {
        $auth = registerAuctioneer();
        asAuctioneer($auth)->post('/api/v1/auctioneer/storefront/logo', ['logo' => UploadedFile::fake()->image('l.png', 200, 100)], ['Accept' => 'application/json']);

        asAuctioneer($auth)->deleteJson('/api/v1/auctioneer/storefront/logo')->assertNoContent();
        asAuctioneer($auth)->getJson('/api/v1/auctioneer/storefront')->assertJsonPath('logo.kind', 'none')->assertJsonPath('logo.url', null);
    });
});

describe('categorias (RF-B07)', function () {
    it('cria, renomeia, reordena e apaga, com endereço único na loja', function () {
        $auth = registerAuctioneer();
        $a = asAuctioneer($auth)->postJson('/api/v1/auctioneer/categories', ['name' => 'Relógios de Bolso'])
            ->assertCreated()->assertJsonPath('slug', 'relogios-de-bolso')->assertJsonPath('position', 1)->json('id');
        $b = asAuctioneer($auth)->postJson('/api/v1/auctioneer/categories', ['name' => 'Relógios de bolso!'])
            ->assertCreated()->assertJsonPath('slug', 'relogios-de-bolso-2')->json('id');

        asAuctioneer($auth)->post("/api/v1/auctioneer/categories/{$b}", ['name' => 'Cronógrafos'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('slug', 'cronografos');
        asAuctioneer($auth)->putJson('/api/v1/auctioneer/categories/order', ['ids' => [$b, $a]])
            ->assertOk()->assertJsonPath('0.name', 'Cronógrafos')->assertJsonPath('1.name', 'Relógios de Bolso');
        asAuctioneer($auth)->deleteJson("/api/v1/auctioneer/categories/{$a}")->assertNoContent();

        asAuctioneer($auth)->getJson('/api/v1/auctioneer/categories')->assertJsonCount(1);
    });

    it('aceita imagem opcional', function () {
        $auth = registerAuctioneer();

        asAuctioneer($auth)->post('/api/v1/auctioneer/categories', ['name' => 'Moda', 'image' => UploadedFile::fake()->image('c.jpg', 1000, 1000)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJson(fn ($json) => $json->where('image_url', fn (?string $url) => str_ends_with((string) $url, '.webp'))->etc());
    });
});

describe('banners (RF-B06)', function () {
    it('exige a imagem do celular e o texto alternativo', function () {
        $auth = registerAuctioneer();

        asAuctioneer($auth)->post('/api/v1/auctioneer/banners', [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'image_mobile']);
    });

    it('cria com período, link e as duas imagens', function () {
        $auth = registerAuctioneer();

        asAuctioneer($auth)->post('/api/v1/auctioneer/banners', [
            'title' => 'Leilão de Natal',
            'image_mobile' => UploadedFile::fake()->image('m.jpg', 2000, 2500),
            'image_desktop' => UploadedFile::fake()->image('d.jpg', 3000, 1000),
            'link_url' => '/leiloes-silva/leiloes',
            'starts_at' => '2026-12-01T00:00:00-03:00',
            'ends_at' => '2026-12-25T00:00:00-03:00',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('starts_at', '2026-12-01T03:00:00Z')
            ->assertJsonPath('showing_now', false)
            ->assertJsonPath('link_url', '/leiloes-silva/leiloes');
    });

    it('recusa link que não é https nem da própria loja e período invertido', function () {
        $auth = registerAuctioneer();
        $base = ['title' => 'Promoção', 'image_mobile' => UploadedFile::fake()->image('m.jpg', 100, 100)];

        asAuctioneer($auth)->post('/api/v1/auctioneer/banners', $base + ['link_url' => 'javascript:alert(1)'], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['link_url']);
        asAuctioneer($auth)->post('/api/v1/auctioneer/banners', $base + ['starts_at' => '2026-12-10', 'ends_at' => '2026-12-01'], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.ends_at.0', 'O fim precisa ser depois do início.');
    });

    it('limita a 10 banners por loja', function () {
        $auth = registerAuctioneer();
        $tenant = app(CurrentTenant::class);
        $tenant->runAs($auth['user']->auctioneer_id, function () {
            for ($i = 0; $i < 10; $i++) {
                BannerRecord::query()->create(['title' => "B{$i}", 'image_mobile' => 'x.webp']);
            }
        });

        asAuctioneer($auth)->post('/api/v1/auctioneer/banners', ['title' => 'Mais um', 'image_mobile' => UploadedFile::fake()->image('m.jpg', 100, 100)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.image_mobile.0', 'Sua loja já tem o máximo de 10 banners.');
    });
});

describe('multi-tenant (specs/04, T-04)', function () {
    it('um leiloeiro não vê, não altera e não apaga o que é de outra loja', function () {
        $maria = registerAuctioneer();
        $joao = registerAuctioneer(['email' => 'joao@ex.com', 'document' => '12.ABC.345/01DE-35', 'slug' => 'joao-leiloes']);
        $catMaria = asAuctioneer($maria)->postJson('/api/v1/auctioneer/categories', ['name' => 'Da Maria'])->json('id');
        app('auth')->forgetGuards();
        app(CurrentTenant::class)->clear();

        asAuctioneer($joao)->getJson('/api/v1/auctioneer/categories')->assertOk()->assertJsonCount(0);
        asAuctioneer($joao)->post("/api/v1/auctioneer/categories/{$catMaria}", ['name' => 'Roubada'], ['Accept' => 'application/json'])->assertNotFound();
        asAuctioneer($joao)->deleteJson("/api/v1/auctioneer/categories/{$catMaria}")->assertNotFound();
        asAuctioneer($joao)->postJson('/api/v1/auctioneer/categories', ['name' => 'Da Maria'])->assertJsonPath('slug', 'da-maria');

        app(CurrentTenant::class)->runAs($maria['user']->auctioneer_id, fn () => expect(CategoryRecord::query()->sole()->name)->toBe('Da Maria'));
    });

    it('sem loja definida, a consulta falha em vez de devolver dados de todas', function () {
        app(CurrentTenant::class)->clear();

        CategoryRecord::query()->get();
    })->throws(MissingTenant::class);
});

describe('loja pública (RF-E01)', function () {
    function publishStore(array $auth): void
    {
        app(AuctioneerEmailVerifier::class)->verify($auth['user']);
        asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'watches', 'socials' => ['instagram' => 'https://instagram.com/x']])->assertOk();
    }

    it('devolve tema, contato, banners no ar e categorias', function () {
        $auth = registerAuctioneer();
        publishStore($auth);
        asAuctioneer($auth)->postJson('/api/v1/auctioneer/categories', ['name' => 'Relógios']);
        asAuctioneer($auth)->post('/api/v1/auctioneer/banners', ['title' => 'No ar', 'image_mobile' => UploadedFile::fake()->image('m.jpg', 100, 100)], ['Accept' => 'application/json']);
        asAuctioneer($auth)->post('/api/v1/auctioneer/banners', ['title' => 'Futuro', 'image_mobile' => UploadedFile::fake()->image('m.jpg', 100, 100), 'starts_at' => now()->addDay()->toIso8601String()], ['Accept' => 'application/json']);
        app('auth')->forgetGuards();
        app(CurrentTenant::class)->clear();

        $this->withoutToken()->getJson('/api/v1/storefronts/leiloes-silva')
            ->assertOk()
            ->assertJsonPath('display_name', 'Leilões Silva')
            ->assertJsonPath('theme.template', 'watches')
            ->assertJsonPath('theme.background', 'ivory')
            ->assertJsonPath('contact.whatsapp', '(11) 98765-4321')
            ->assertJsonPath('contact.whatsapp_url', 'https://wa.me/5511987654321')
            ->assertJsonPath('contact.socials.instagram', 'https://instagram.com/x')
            ->assertJsonCount(1, 'banners')
            ->assertJsonPath('banners.0.title', 'No ar')
            ->assertJsonPath('categories.0.slug', 'relogios')
            ->assertJsonMissingPath('document_masked');
    });

    it('loja fora do ar (e-mail não confirmado ou sem modelo) não aparece', function () {
        $auth = registerAuctioneer();
        asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'watches']);
        app('auth')->forgetGuards();

        $this->withoutToken()->getJson('/api/v1/storefronts/leiloes-silva')->assertNotFound();
        $this->getJson('/api/v1/storefronts/nao-existe')->assertNotFound();
    });

    it('endereço antigo redireciona para o novo por 90 dias (RF-A06)', function () {
        $auth = registerAuctioneer();
        publishStore($auth);
        asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/slug', ['slug' => 'casa-silva'])->assertOk();
        app('auth')->forgetGuards();

        $this->withoutToken()->getJson('/api/v1/storefronts/leiloes-silva')
            ->assertStatus(301)
            ->assertJsonPath('redirect_to', 'casa-silva');

        $this->getJson('/api/v1/storefronts/leiloes-silva/status')->assertOk()->assertExactJson(['state' => 'moved', 'slug' => 'casa-silva']);
        $this->getJson('/api/v1/storefronts/CASA-SILVA/status')->assertExactJson(['state' => 'live', 'slug' => 'casa-silva']);

        $this->travel(91)->days();
        $this->getJson('/api/v1/storefronts/leiloes-silva')->assertNotFound();
        $this->getJson('/api/v1/storefronts/leiloes-silva/status')->assertExactJson(['state' => 'missing', 'slug' => null]);
    });

    it('avisa o site para atualizar o cache da loja quando algo muda', function () {
        config(['doleleilao.revalidate_secret' => 'segredo']);
        Http::fake(['*/api/revalidate' => Http::response(['ok' => true])]);
        $auth = registerAuctioneer();
        publishStore($auth);
        asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/slug', ['slug' => 'casa-silva'])->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:3000/api/revalidate'
            && $request->header('X-Revalidate-Secret') === ['segredo']
            && $request['tags'] === ['storefront:casa-silva', 'storefront:leiloes-silva']);
    });
});
