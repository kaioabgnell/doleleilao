<?php

declare(strict_types=1);

use DoleLeilao\Identity\Infrastructure\Notifications\ResetAuctioneerPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('envia o link de nova senha para o site (RF-A05)', function () {
    $auth = registerAuctioneer();

    $this->postJson('/api/v1/auctioneer/password/forgot', ['email' => 'maria@leiloessilva.com.br'])
        ->assertAccepted();

    Notification::assertSentTo($auth['user'], ResetAuctioneerPassword::class, function (ResetAuctioneerPassword $n) use ($auth) {
        return str_starts_with($n->url($auth['user']), 'http://localhost:3000/redefinir-senha?token=');
    });
});

it('responde igual para e-mail não cadastrado', function () {
    $this->postJson('/api/v1/auctioneer/password/forgot', ['email' => 'ninguem@ex.com'])
        ->assertAccepted()
        ->assertJsonPath('message', 'Se este e-mail estiver cadastrado, você vai receber um link para criar uma nova senha.');

    Notification::assertNothingSent();
});

it('troca a senha, encerra as sessões abertas e confirma o e-mail', function () {
    $auth = registerAuctioneer();
    $this->postJson('/api/v1/auctioneer/password/forgot', ['email' => 'maria@leiloessilva.com.br']);
    $token = null;
    Notification::assertSentTo($auth['user'], ResetAuctioneerPassword::class, function (ResetAuctioneerPassword $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->postJson('/api/v1/auctioneer/password/reset', [
        'token' => $token,
        'email' => 'maria@leiloessilva.com.br',
        'password' => 'nova-senha-456',
    ])->assertOk();

    app('auth')->forgetGuards();
    asAuctioneer($auth)->getJson('/api/v1/auctioneer/me')->assertUnauthorized();
    $this->postJson('/api/v1/auctioneer/sessions', ['email' => 'maria@leiloessilva.com.br', 'password' => 'nova-senha-456'])
        ->assertOk()
        ->assertJsonPath('user.email_verified', true);
});

it('recusa token inválido ou vencido (60 minutos)', function () {
    $auth = registerAuctioneer();
    $this->postJson('/api/v1/auctioneer/password/forgot', ['email' => 'maria@leiloessilva.com.br']);
    $token = null;
    Notification::assertSentTo($auth['user'], ResetAuctioneerPassword::class, function (ResetAuctioneerPassword $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->travel(61)->minutes();

    $this->postJson('/api/v1/auctioneer/password/reset', [
        'token' => $token, 'email' => 'maria@leiloessilva.com.br', 'password' => 'nova-senha-456',
    ])->assertUnprocessable()->assertJsonPath('errors.token.0', 'Este link é inválido ou expirou. Peça um novo.');
});
