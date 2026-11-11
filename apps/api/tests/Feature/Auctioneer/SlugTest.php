<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('confere a disponibilidade do endereço ao vivo (RF-A02)', function (string $slug, bool $available, ?string $reason) {
    registerAuctioneer();

    $this->getJson('/api/v1/auctioneers/slug-availability?slug='.urlencode($slug))
        ->assertOk()
        ->assertJsonPath('available', $available)
        ->assertJsonPath('reason', $reason);
})->with([
    ['casa-nobre', true, null],
    ['leiloes-silva', false, 'taken'],
    ['admin', false, 'reserved'],
    ['Casa Nobre', false, 'invalid'],
]);

it('sugere o primeiro endereço livre e o nome de exibição a partir do nome da empresa', function () {
    registerAuctioneer(['slug' => 'leiloes-silva-filhos-ltda']);

    $this->getJson('/api/v1/auctioneers/suggestions?company_name='.urlencode('Leilões Silva & Filhos Ltda'))
        ->assertOk()
        ->assertJsonPath('slug', 'leiloes-silva-filhos-ltda-2')
        ->assertJsonPath('display_name', 'Leilões Silva');
});

it('troca o endereço e o antigo fica reservado para a loja por 90 dias (RF-A06, D-29)', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/slug', ['slug' => 'casa-silva'])
        ->assertOk()
        ->assertJsonPath('slug', 'casa-silva')
        ->assertJsonPath('store_url', 'http://localhost:3000/casa-silva');

    app('auth')->forgetGuards();
    $this->withoutToken()->getJson('/api/v1/auctioneers/slug-availability?slug=leiloes-silva')
        ->assertJsonPath('available', false)
        ->assertJsonPath('reason', 'taken');

    $this->postJson('/api/v1/auctioneers', auctioneerPayload([
        'email' => 'outro@ex.com', 'document' => '12.ABC.345/01DE-35', 'slug' => 'leiloes-silva',
    ]))->assertUnprocessable()->assertJsonPath('errors.slug.0', 'Este endereço já está em uso. Escolha outro.');

    $this->travel(91)->days();
    $this->getJson('/api/v1/auctioneers/slug-availability?slug=leiloes-silva')->assertJsonPath('available', true);
});

it('permite 1 troca a cada 30 dias', function () {
    $auth = registerAuctioneer();
    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/slug', ['slug' => 'casa-silva'])
        ->assertOk()
        ->assertJsonPath('next_slug_change_at', now()->addDays(30)->utc()->format('Y-m-d\TH:i:s\Z'));

    $this->travel(10)->days();
    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/slug', ['slug' => 'casa-silva-2'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.slug.0', 'O endereço da loja só pode ser trocado 1 vez a cada 30 dias.');

    $this->travel(21)->days();
    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/slug', ['slug' => 'casa-silva-2'])->assertOk();
});

it('a loja pode voltar para o próprio endereço antigo', function () {
    $auth = registerAuctioneer();
    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/slug', ['slug' => 'casa-silva'])->assertOk();
    $this->travel(31)->days();

    asAuctioneer($auth)->getJson('/api/v1/auctioneers/slug-availability?slug=leiloes-silva')->assertJsonPath('available', true);
    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/slug', ['slug' => 'leiloes-silva'])
        ->assertOk()
        ->assertJsonPath('slug', 'leiloes-silva');
});

it('recusa o endereço atual, ocupado ou reservado', function (string $slug, string $message) {
    registerAuctioneer(['email' => 'joao@ex.com', 'document' => '12.ABC.345/01DE-35', 'slug' => 'joao-leiloes']);
    app('auth')->forgetGuards();
    $auth = registerAuctioneer();

    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/slug', ['slug' => $slug])
        ->assertUnprocessable()
        ->assertJsonPath('errors.slug.0', $message);
})->with([
    ['leiloes-silva', 'Este já é o endereço da sua loja.'],
    ['joao-leiloes', 'Este endereço já está em uso. Escolha outro.'],
    ['termos', 'Este endereço é reservado. Escolha outro.'],
]);
