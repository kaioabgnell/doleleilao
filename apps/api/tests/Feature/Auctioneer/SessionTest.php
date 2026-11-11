<?php

declare(strict_types=1);

use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('entra com e-mail e senha e recebe o token do painel (RF-A05)', function () {
    registerAuctioneer();

    $response = $this->postJson('/api/v1/auctioneer/sessions', [
        'email' => ' Maria@LeiloesSilva.com.br ',
        'password' => 'senha-forte-123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'expires_at', 'user', 'auctioneer'])
        ->assertJsonPath('auctioneer.slug', 'leiloes-silva');

    $this->withToken((string) $response->json('token'))
        ->getJson('/api/v1/auctioneer/me')
        ->assertOk()
        ->assertJsonPath('user.name', 'Maria Silva')
        ->assertJsonPath('auctioneer.display_name', 'Leilões Silva');
});

it('recusa senha errada e e-mail desconhecido com a mesma mensagem', function (string $email, string $password) {
    registerAuctioneer();

    $this->postJson('/api/v1/auctioneer/sessions', compact('email', 'password'))
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'E-mail ou senha incorretos.');
})->with([
    ['maria@leiloessilva.com.br', 'senha-errada-1'],
    ['ninguem@exemplo.com', 'senha-forte-123'],
]);

it('limita a 5 tentativas de login por minuto por e-mail e IP (specs/04 segurança)', function () {
    registerAuctioneer();
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auctioneer/sessions', ['email' => 'maria@leiloessilva.com.br', 'password' => 'errada-123']);
    }

    $this->postJson('/api/v1/auctioneer/sessions', ['email' => 'maria@leiloessilva.com.br', 'password' => 'senha-forte-123'])
        ->assertTooManyRequests();
});

it('sai da conta: o token deixa de valer', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->deleteJson('/api/v1/auctioneer/sessions/current')->assertNoContent();

    app('auth')->forgetGuards();
    asAuctioneer($auth)->getJson('/api/v1/auctioneer/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('exige token para as rotas do painel', function () {
    $this->getJson('/api/v1/auctioneer/me')
        ->assertUnauthorized()
        ->assertJsonPath('detail', 'Entre na sua conta para continuar.');
});

it('leiloeiro suspenso não entra nem usa o painel (D-28)', function () {
    $auth = registerAuctioneer();
    AuctioneerRecord::query()->update(['status' => 'suspended']);

    $this->postJson('/api/v1/auctioneer/sessions', ['email' => 'maria@leiloessilva.com.br', 'password' => 'senha-forte-123'])
        ->assertForbidden()
        ->assertJsonPath('detail', 'Esta conta está suspensa. Fale com o suporte do Dole Leilão.');

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/me')->assertForbidden();
});
