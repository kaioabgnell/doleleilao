<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
| Ajudantes dos testes de feature.
*/

use DoleLeilao\Identity\Infrastructure\AuctioneerEmailVerifier;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** CPF válido gerado a partir de um número (para cadastros diferentes no mesmo teste). */
function validCpf(int $seed): string
{
    $base = str_pad((string) (100000000 + $seed), 9, '0', STR_PAD_LEFT);
    foreach ([9, 10] as $length) {
        $sum = 0;
        for ($i = 0; $i < $length; $i++) {
            $sum += (int) $base[$i] * ($length + 1 - $i);
        }
        $base .= (string) (($sum * 10) % 11 % 10);
    }

    return $base;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function auctioneerPayload(array $overrides = []): array
{
    return [
        'name' => 'Maria Silva',
        'email' => 'maria@leiloessilva.com.br',
        'phone' => '(11) 98765-4321',
        'password' => 'senha-forte-123',
        'document_type' => 'cnpj',
        'document' => '11.222.333/0001-81',
        'company_name' => 'Leilões Silva & Filhos Ltda',
        'slug' => 'leiloes-silva',
        'accept_terms' => true,
        ...$overrides,
    ];
}

/**
 * Cadastra um leiloeiro pela API e devolve o token e o usuário dono.
 *
 * @param  array<string, mixed>  $overrides
 * @return array{token: string, user: AuctioneerUser}
 */
function registerAuctioneer(array $overrides = [], bool $verified = false): array
{
    $response = test()->postJson('/api/v1/auctioneers', auctioneerPayload($overrides))->assertCreated();
    $user = AuctioneerUser::query()->where('public_id', $response->json('user.id'))->firstOrFail();

    if ($verified) {
        app(AuctioneerEmailVerifier::class)->verify($user);
        $user->refresh();
    }

    return ['token' => (string) $response->json('token'), 'user' => $user];
}

/** @param array{token: string} $auth */
function asAuctioneer(array $auth): Illuminate\Foundation\Testing\TestCase
{
    return test()->withToken($auth['token']);
}

/** Loja pronta: e-mail validado e modelo escolhido (fica pública). */
function liveStore(array $overrides = []): array
{
    $auth = registerAuctioneer($overrides, verified: true);
    app('auth')->forgetGuards();
    test()->withToken($auth['token'])->patchJson('/api/v1/auctioneer/storefront', ['template' => 'watches'])->assertOk();

    return $auth;
}

/**
 * Cria um produto (com foto) pelo painel e devolve o JSON.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function createProduct(array $auth, array $overrides = [], bool $available = true): array
{
    $product = test()->withToken($auth['token'])->postJson('/api/v1/auctioneer/products', [
        'title' => 'Rolex Datejust 1985',
        'starting_price_cents' => 150000,
        'increment_cents' => 10000,
        ...$overrides,
    ])->assertCreated()->json();

    test()->withToken($auth['token'])->post("/api/v1/auctioneer/products/{$product['id']}/photos", [
        'photos' => [UploadedFile::fake()->image('foto.jpg', 1200, 900)],
    ], ['Accept' => 'application/json'])->assertCreated();

    if ($available) {
        test()->withToken($auth['token'])->patchJson("/api/v1/auctioneer/products/{$product['id']}", ['status' => 'available'])->assertOk();
    }

    return test()->withToken($auth['token'])->getJson("/api/v1/auctioneer/products/{$product['id']}")->json();
}

/** @return array{token: string, bidder: array<string, mixed>} */
function registerBidder(string $slug = 'leiloes-silva', array $overrides = []): array
{
    $response = test()->withoutToken()->postJson("/api/v1/storefronts/{$slug}/bidders", [
        'name' => 'Ana Paula Souza',
        'email' => 'ana@exemplo.com',
        'phone' => '(21) 99876-5432',
        'cpf' => '529.982.247-25',
        'password' => 'senha-forte-1',
        'accept_terms' => true,
        ...$overrides,
    ])->assertCreated();

    return ['token' => (string) $response->json('token'), 'bidder' => (array) $response->json('bidder')];
}

/*
| Ajudantes do leilão e do chat (Fases 3 e 4).
*/

function as_(string $token): Illuminate\Foundation\Testing\TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

/** Participante com cadastro completo (e-mail validado). @return array{token: string, id: string} */
function eligibleBidder(int $n, string $slug = 'leiloes-silva', bool $verified = true): array
{
    app('auth')->forgetGuards();
    $b = registerBidder($slug, ['email' => "pessoa{$n}@exemplo.com", 'cpf' => validCpf(500 + $n), 'name' => "Pessoa{$n} Teste"]);
    if ($verified) {
        BidderUser::query()->where('public_id', $b['bidder']['id'])->firstOrFail()->markEmailVerified();
    }

    return ['token' => $b['token'], 'id' => (string) $b['bidder']['id']];
}

/** @param list<array<string, mixed>> $products */
function draftAuction(array $auth, array $products, string $startsAt = '2026-11-21T20:00:00-03:00', array $extra = []): array
{
    return as_($auth['token'])->postJson('/api/v1/auctioneer/auctions', [
        'name' => 'Relógios de Coleção',
        'description' => 'Peças raras.',
        'starts_at' => $startsAt,
        'settings' => ['item_duration_s' => 60, 'extend_window_s' => 15, 'extend_by_s' => 15, 'gap_s' => 10],
        'items' => array_map(fn ($p) => ['product_id' => $p['id']], $products),
        ...$extra,
    ])->assertCreated()->json();
}

function tick(): void
{
    test()->artisan('auction:clock', ['--once' => true])->assertSuccessful();
}

function bid(string $token, string $item, int $cents, ?string $key = null): TestResponse
{
    return as_($token)->postJson("/api/v1/items/{$item}/bids", ['amount_cents' => $cents], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
}

/** Cartão de crédito em Configurações → Pagamento: exigido para agendar lotes (D-54). */
function withPaymentCard(array $auth, string $number = '4111 1111 1111 1111'): void
{
    as_($auth['token'])->putJson('/api/v1/auctioneer/billing/card', [
        'number' => $number,
        'holder_name' => 'Maria Silva',
        'expiry' => '12/30',
        'cvv' => '123',
    ])->assertOk();
}

/** Lote agendado com 2 produtos, começando às 20h de amanhã (Brasília) = 23:00 UTC. */
function scheduledAuction(): array
{
    $auth = liveStore();
    withPaymentCard($auth);
    $p1 = createProduct($auth, ['title' => 'Rolex Datejust 1985']);
    $p2 = createProduct($auth, ['title' => 'Omega Speedmaster', 'starting_price_cents' => 80000, 'increment_cents' => 5000]);
    $auction = draftAuction($auth, [$p1, $p2]);
    as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/schedule")->assertOk();

    return ['auth' => $auth, 'auction' => $auction, 'items' => $auction['items'], 'products' => [$p1, $p2]];
}
