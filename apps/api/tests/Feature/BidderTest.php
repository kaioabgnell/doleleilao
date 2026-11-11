<?php

declare(strict_types=1);

use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Identity\Infrastructure\Notifications\ResetBidderPassword;
use DoleLeilao\Identity\Infrastructure\Notifications\VerifyBidderEmail;
use DoleLeilao\Notification\Application\SendNotification;
use DoleLeilao\Notification\Domain\AuctionRef;
use DoleLeilao\Notification\Domain\Notification as Notice;
use DoleLeilao\Notification\Domain\Recipient;
use DoleLeilao\Storefront\Infrastructure\ConsignmentRequested;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake('public');
    Storage::fake('local');
});

it('cadastra o participante na loja com CPF obrigatório e manda a validação de e-mail (RF-E03, RF-E04)', function () {
    liveStore();
    app('auth')->forgetGuards();

    $auth = registerBidder();

    expect($auth['bidder'])->toMatchArray([
        'name' => 'Ana Paula Souza', 'nickname' => 'Ana S.', 'cpf_masked' => '***.982.247-**',
        'phone' => '(21) 99876-5432', 'eligible' => false, 'missing' => ['email'],
    ]);
    $bidder = BidderUser::query()->sole();
    expect(DB::table('bidders')->value('cpf_enc'))->not->toContain('52998224725');
    Notification::assertSentTo($bidder, VerifyBidderEmail::class);
});

it('o CPF e o e-mail são únicos por loja, mas a mesma pessoa pode ter conta em outra loja (D-11)', function () {
    liveStore();
    liveStore(['email' => 'joao@ex.com', 'document' => '12.ABC.345/01DE-35', 'slug' => 'joao-leiloes']);
    app('auth')->forgetGuards();
    registerBidder();

    $this->withoutToken()->postJson('/api/v1/storefronts/leiloes-silva/bidders', [
        'name' => 'Outra', 'email' => 'ANA@exemplo.com', 'phone' => '21999990000', 'cpf' => '52998224725', 'password' => 'senha-forte-1', 'accept_terms' => true,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Este e-mail já tem cadastro nesta loja. Entre com ele ou recupere a senha.')
        ->assertJsonPath('errors.cpf.0', 'Este CPF já tem cadastro nesta loja.');

    registerBidder('joao-leiloes');
    expect(BidderUser::query()->count())->toBe(2);
});

it('recusa CPF inválido e loja fora do ar', function () {
    liveStore();
    app('auth')->forgetGuards();

    $this->withoutToken()->postJson('/api/v1/storefronts/leiloes-silva/bidders', ['name' => 'Ana', 'email' => 'a@b.com', 'phone' => '21999990000', 'cpf' => '111.111.111-11', 'password' => 'senha-forte-1', 'accept_terms' => true])
        ->assertUnprocessable()->assertJsonPath('errors.cpf.0', 'CPF inválido.');
    $this->postJson('/api/v1/storefronts/nao-existe/bidders', [])->assertNotFound();
});

it('confirma o e-mail e o cadastro fica completo (habilitado, D-23)', function () {
    liveStore();
    app('auth')->forgetGuards();
    $auth = registerBidder();
    $bidder = BidderUser::query()->sole();

    $this->get(VerifyBidderEmail::url($bidder))->assertRedirect('http://localhost:3000/leiloes-silva/minha-conta?email=verificado');

    $this->withToken($auth['token'])->getJson('/api/v1/bidders/me/eligibility')->assertExactJson(['eligible' => true, 'missing' => []]);
    expect($bidder->refresh()->profile_completed_at)->not->toBeNull();
});

it('o login vale só na loja do cadastro', function () {
    liveStore();
    liveStore(['email' => 'joao@ex.com', 'document' => '12.ABC.345/01DE-35', 'slug' => 'joao-leiloes']);
    app('auth')->forgetGuards();
    registerBidder();

    $this->withoutToken()->postJson('/api/v1/storefronts/leiloes-silva/sessions', ['email' => 'ana@exemplo.com', 'password' => 'senha-forte-1'])->assertOk()->assertJsonStructure(['token', 'bidder']);
    $this->postJson('/api/v1/storefronts/joao-leiloes/sessions', ['email' => 'ana@exemplo.com', 'password' => 'senha-forte-1'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'E-mail ou senha incorretos.');
});

it('token de participante não abre o painel do leiloeiro, e vice-versa', function () {
    $owner = liveStore();
    app('auth')->forgetGuards();
    $bidder = registerBidder();

    $this->withToken($bidder['token'])->getJson('/api/v1/auctioneer/me')->assertForbidden();
    app('auth')->forgetGuards();
    $this->withToken($owner['token'])->getJson('/api/v1/bidders/me')->assertUnauthorized();
});

it('troca a senha pelo link da loja (60 min) e encerra as sessões', function () {
    liveStore();
    app('auth')->forgetGuards();
    $auth = registerBidder();
    $this->withoutToken()->postJson('/api/v1/storefronts/leiloes-silva/password/forgot', ['email' => 'ana@exemplo.com'])->assertAccepted();
    $token = null;
    Notification::assertSentTo(BidderUser::query()->sole(), ResetBidderPassword::class, function (ResetBidderPassword $n) use (&$token) {
        $token = $n->token;

        return str_starts_with($n->url(BidderUser::query()->sole()), 'http://localhost:3000/leiloes-silva/redefinir-senha?token=');
    });

    $this->postJson('/api/v1/storefronts/leiloes-silva/password/reset', ['email' => 'ana@exemplo.com', 'token' => 'errado', 'password' => 'nova-senha-2'])->assertUnprocessable();
    $this->postJson('/api/v1/storefronts/leiloes-silva/password/reset', ['email' => 'ana@exemplo.com', 'token' => $token, 'password' => 'nova-senha-2'])->assertOk();

    app('auth')->forgetGuards();
    $this->withToken($auth['token'])->getJson('/api/v1/bidders/me')->assertUnauthorized();
});

it('escolhe avatar da galeria ou envia foto (D-38)', function () {
    liveStore();
    app('auth')->forgetGuards();
    $auth = registerBidder();

    $this->withToken($auth['token'])->putJson('/api/v1/bidders/me/avatar', ['avatar_key' => 'avatar-07'])->assertOk()->assertJsonPath('avatar_key', 'avatar-07');
    $this->withToken($auth['token'])->putJson('/api/v1/bidders/me/avatar', ['avatar_key' => 'avatar-99'])->assertUnprocessable();
    $this->withToken($auth['token'])->post('/api/v1/bidders/me/photo', ['photo' => UploadedFile::fake()->image('eu.jpg', 1000, 1000)], ['Accept' => 'application/json'])
        ->assertOk()->assertJson(fn ($json) => $json->where('photo_url', fn (?string $u) => str_ends_with((string) $u, '.webp'))->etc());
    $this->withToken($auth['token'])->patchJson('/api/v1/bidders/me', ['nickname' => 'Aninha'])->assertOk()->assertJsonPath('nickname', 'Aninha');
});

it('favorita produtos públicos da própria loja (RF-E05)', function () {
    $owner = liveStore();
    $product = createProduct($owner);
    $draft = createProduct($owner, ['title' => 'Rascunho'], available: false);
    app('auth')->forgetGuards();
    $auth = registerBidder();

    $this->withToken($auth['token'])->putJson("/api/v1/bidders/me/favorites/{$product['id']}")->assertOk()->assertJsonPath('favorite', true);
    $this->withToken($auth['token'])->putJson("/api/v1/bidders/me/favorites/{$product['id']}")->assertOk();
    $this->withToken($auth['token'])->putJson("/api/v1/bidders/me/favorites/{$draft['id']}")->assertNotFound();
    $this->withToken($auth['token'])->getJson('/api/v1/bidders/me/favorites')->assertJsonPath('ids', [$product['id']]);
    $this->withToken($auth['token'])->deleteJson("/api/v1/bidders/me/favorites/{$product['id']}")->assertJsonPath('favorite', false);
});

it('o participante tem o próprio sino (D-43)', function () {
    liveStore();
    app('auth')->forgetGuards();
    $auth = registerBidder();
    $bidder = BidderUser::query()->sole();
    app(SendNotification::class)->handle(Notice::participationEnabled(
        Recipient::bidder($bidder->id, $bidder->auctioneer_id),
        new AuctionRef('01jx', 'Relógios de Sábado', now()->addDays(2)->toDateTimeImmutable(), '/leiloes-silva/leilao/sabado'),
    ));

    $this->withToken($auth['token'])->getJson('/api/v1/bidders/me/notifications')
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('data.0.title', 'Seu cadastro está habilitado para participar do leilão Relógios de Sábado');
    $this->withToken($auth['token'])->postJson('/api/v1/bidders/me/notifications/read')->assertJsonPath('unread_count', 0);
});

it('"quero leiloar": chega no painel, no sino e no e-mail do leiloeiro (RF-E09)', function () {
    $owner = liveStore();
    app('auth')->forgetGuards();

    $this->withoutToken()->post('/api/v1/storefronts/leiloes-silva/consignment-requests', [
        'name' => 'Carlos', 'phone' => '(11) 91234-5678',
        'description' => 'Tenho um relógio Omega dos anos 60 que quero leiloar.',
        'photos' => [UploadedFile::fake()->image('omega.jpg', 800, 800)],
    ], ['Accept' => 'application/json'])->assertCreated();
    $this->postJson('/api/v1/storefronts/leiloes-silva/consignment-requests', ['name' => 'Sem contato', 'description' => 'Uma descrição com mais de vinte letras.'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Informe um e-mail ou um telefone para o leiloeiro responder.');

    Notification::assertSentTo($owner['user'], ConsignmentRequested::class);
    asAuctioneer($owner)->getJson('/api/v1/auctioneer/consignment-requests')
        ->assertJsonPath('new_count', 1)
        ->assertJsonPath('data.0.whatsapp_url', 'https://wa.me/5511912345678')
        ->assertJsonCount(1, 'data.0.photos');
    asAuctioneer($owner)->getJson('/api/v1/auctioneer/notifications')->assertJsonPath('data.0.kind', 'consignment_request');
});
