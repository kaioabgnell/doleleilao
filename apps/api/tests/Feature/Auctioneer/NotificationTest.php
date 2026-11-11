<?php

declare(strict_types=1);

use DoleLeilao\Identity\Infrastructure\Notifications\VerifyAuctioneerEmail;
use DoleLeilao\Notification\Application\SendNotification;
use DoleLeilao\Notification\Domain\AuctionRef;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Notification\Domain\Recipient;
use DoleLeilao\Notification\Infrastructure\NotificationRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification as Mail;

beforeEach(fn () => Mail::fake());

it('o leiloeiro recém-cadastrado recebe as boas-vindas no sino (RF-N04)', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('next_cursor', null)
        ->assertJsonPath('data.0.kind', 'welcome')
        ->assertJsonPath('data.0.title', 'Boas-vindas ao Dole Leilão!')
        ->assertJsonPath('data.0.url', '/painel')
        ->assertJsonPath('data.0.read', false)
        ->assertJsonStructure(['data' => [['id', 'kind', 'title', 'body', 'url', 'read', 'created_at']]]);
});

it('avisa "Sua loja está no ar" uma única vez, com e-mail confirmado e modelo escolhido em qualquer ordem', function (bool $templateFirst) {
    $auth = registerAuctioneer();
    $chooseTemplate = fn () => asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'watches'])->assertOk();
    $verify = fn () => $this->get(VerifyAuctioneerEmail::url($auth['user']));

    if ($templateFirst) {
        $chooseTemplate();
        expect(NotificationRecord::query()->where('kind', 'store_live')->count())->toBe(0);
        $verify();
    } else {
        $verify();
        expect(NotificationRecord::query()->where('kind', 'store_live')->count())->toBe(0);
        $chooseTemplate();
    }
    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'fashion'])->assertOk();

    $live = NotificationRecord::query()->where('kind', 'store_live')->sole();
    expect($live->body)->toBe('Compartilhe o link com seus clientes: localhost:3000/leiloes-silva.');
})->with(['modelo antes do e-mail' => true, 'e-mail antes do modelo' => false]);

it('marca uma ou todas como lidas', function () {
    $auth = registerAuctioneer();
    app(SendNotification::class)->handle(Notification::storeLive(
        Recipient::auctioneerUser($auth['user']->id, $auth['user']->auctioneer_id), 'x/y',
    ));
    $ids = asAuctioneer($auth)->getJson('/api/v1/auctioneer/notifications')->json('data.*.id');

    asAuctioneer($auth)->postJson('/api/v1/auctioneer/notifications/read', ['ids' => [$ids[0]]])
        ->assertOk()->assertJsonPath('unread_count', 1);
    asAuctioneer($auth)->postJson('/api/v1/auctioneer/notifications/read')
        ->assertOk()->assertJsonPath('unread_count', 0);
    asAuctioneer($auth)->getJson('/api/v1/auctioneer/notifications')->assertJsonPath('data.1.read', true);
});

it('pagina de 20 em 20, mais recentes primeiro (RF-N02)', function () {
    $auth = registerAuctioneer();
    $to = Recipient::auctioneerUser($auth['user']->id, $auth['user']->auctioneer_id);
    $send = app(SendNotification::class);
    for ($i = 1; $i <= 24; $i++) {
        $send->handle(Notification::newParticipant($to, new AuctionRef('a', 'Lote', now()->toDateTimeImmutable(), '/x'), "Pessoa {$i}", (string) $i));
    }

    $first = asAuctioneer($auth)->getJson('/api/v1/auctioneer/notifications')->assertOk();
    expect($first->json('data'))->toHaveCount(20)
        ->and($first->json('data.0.body'))->toBe('Pessoa 24 vai participar.')
        ->and($first->json('unread_count'))->toBe(25);

    $second = asAuctioneer($auth)->getJson('/api/v1/auctioneer/notifications?cursor='.$first->json('next_cursor'))->assertOk();
    expect($second->json('data'))->toHaveCount(5)
        ->and($second->json('data.4.kind'))->toBe('welcome')
        ->and($second->json('next_cursor'))->toBeNull();
});

it('não repete o mesmo aviso', function () {
    $auth = registerAuctioneer();
    $send = app(SendNotification::class);
    $to = Recipient::auctioneerUser($auth['user']->id, $auth['user']->auctioneer_id);

    expect($send->handle(Notification::welcome($to)))->toBeFalse();
    expect(NotificationRecord::query()->count())->toBe(1);
});

it('cada conta só vê e marca as próprias notificações (RF-N07)', function () {
    $maria = registerAuctioneer();
    $joao = registerAuctioneer(['email' => 'joao@ex.com', 'document' => '12.ABC.345/01DE-35', 'slug' => 'joao-leiloes']);
    $mariaId = asAuctioneer($maria)->getJson('/api/v1/auctioneer/notifications')->json('data.0.id');
    app('auth')->forgetGuards();

    asAuctioneer($joao)->postJson('/api/v1/auctioneer/notifications/read', ['ids' => [$mariaId]])->assertOk();
    asAuctioneer($joao)->getJson('/api/v1/auctioneer/notifications')->assertJsonCount(1, 'data');
    app('auth')->forgetGuards();

    asAuctioneer($maria)->getJson('/api/v1/auctioneer/notifications')
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('data.0.read', false);
});

it('apaga notificações com mais de 90 dias', function () {
    registerAuctioneer();
    $this->travel(91)->days();

    Artisan::call('model:prune', ['--model' => [NotificationRecord::class]]);

    expect(NotificationRecord::query()->count())->toBe(0);
});
