<?php

declare(strict_types=1);

use DoleLeilao\Storefront\Domain\StoreTerms;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Http::fake();
});

it('começa com o texto padrão, que a loja pública mostra com o nome e o contato da loja', function () {
    $auth = liveStore();

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/storefront/terms')->assertOk()
        ->assertJsonPath('is_default', true)
        ->assertJsonPath('terms', StoreTerms::default()->text)
        ->assertJsonPath('fields.0.key', 'loja');

    $public = $this->getJson('/api/v1/storefronts/leiloes-silva/terms')->assertOk()
        ->assertJsonPath('store', 'Leilões Silva')
        ->json('terms');
    expect($public)
        ->toContain('Estes termos valem para todos os leilões de Leilões Silva.')
        ->toContain('(11) 98765-4321')
        ->toContain('maria@leiloessilva.com.br')
        ->not->toContain('{loja}');
});

it('o leiloeiro troca os termos e pode voltar ao texto padrão', function () {
    $auth = liveStore();
    config(['doleleilao.revalidate_secret' => 'segredo']);

    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/terms', ['terms' => "## Pagamento\nPix em até 24 h na {loja}."])->assertOk()
        ->assertJsonPath('is_default', false)
        ->assertJsonPath('terms', "## Pagamento\nPix em até 24 h na {loja}.");
    $this->getJson('/api/v1/storefronts/leiloes-silva/terms')->assertJsonPath('terms', "## Pagamento\nPix em até 24 h na Leilões Silva.");
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/revalidate'));

    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/terms', ['reset' => true])->assertOk()
        ->assertJsonPath('is_default', true);
});

it('recusa termos vazios', function () {
    $auth = liveStore();

    asAuctioneer($auth)->putJson('/api/v1/auctioneer/storefront/terms', ['terms' => '   '])->assertUnprocessable()
        ->assertJsonPath('errors.terms.0', 'Escreva os termos da loja.');
});

it('loja inexistente responde 404', function () {
    $this->getJson('/api/v1/storefronts/nao-existe/terms')->assertNotFound();
});
