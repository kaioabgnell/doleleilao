<?php

declare(strict_types=1);

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Identity\Infrastructure\Notifications\VerifyAuctioneerEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('o link do e-mail confirma o endereço, ativa a loja e volta para o painel (RF-A04)', function () {
    $auth = registerAuctioneer();

    $this->get(VerifyAuctioneerEmail::url($auth['user']))
        ->assertRedirect('http://localhost:3000/painel?email=verificado');

    $user = $auth['user']->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->auctioneer->status->value)->toBe('active');
});

it('o link expira em 24 horas', function () {
    $auth = registerAuctioneer();
    $url = VerifyAuctioneerEmail::url($auth['user']);

    $this->travel(25)->hours();

    $this->get($url)->assertRedirect('http://localhost:3000/painel?email=link-expirado');
    expect($auth['user']->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('recusa link adulterado', function () {
    $auth = registerAuctioneer();

    $this->get(VerifyAuctioneerEmail::url($auth['user']).'x')
        ->assertRedirect('http://localhost:3000/painel?email=link-expirado');
});

it('reenvia o link para quem ainda não confirmou', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->postJson('/api/v1/auctioneer/email/verification')
        ->assertAccepted()
        ->assertJsonPath('message', 'Enviamos um novo link para maria@leiloessilva.com.br.');

    Notification::assertSentToTimes($auth['user'], VerifyAuctioneerEmail::class, 2);
});

it('não reenvia para quem já confirmou', function () {
    $auth = registerAuctioneer(verified: true);

    asAuctioneer($auth)->postJson('/api/v1/auctioneer/email/verification')
        ->assertOk()
        ->assertJsonPath('message', 'Seu e-mail já está confirmado.');
});

it('a mensagem do e-mail aponta para a API assinada e está em português', function () {
    $auth = registerAuctioneer();
    $mail = (new VerifyAuctioneerEmail)->toMail($auth['user']);

    expect($mail->subject)->toBe('Confirme seu e-mail no Dole Leilão')
        ->and($mail->actionUrl)->toContain('/api/v1/auctioneer/email/verify/'.$auth['user']->public_id)
        ->and($mail->actionUrl)->toContain('signature=');
});

it('ativa só a loja do dono que confirmou', function () {
    registerAuctioneer();
    $other = registerAuctioneer(['email' => 'joao@ex.com', 'document' => '12.ABC.345/01DE-35', 'slug' => 'joao-leiloes']);

    $this->get(VerifyAuctioneerEmail::url($other['user']));

    expect(AuctioneerUser::query()->where('email', 'maria@leiloessilva.com.br')->sole()->auctioneer->status->value)
        ->toBe('pending');
});
