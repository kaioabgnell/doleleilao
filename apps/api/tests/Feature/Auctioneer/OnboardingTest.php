<?php

declare(strict_types=1);

use DoleLeilao\Identity\Infrastructure\Notifications\VerifyAuctioneerEmail;
use DoleLeilao\Storefront\Domain\Onboarding\OnboardingCompleted;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('leiloeiro recém-cadastrado vê os 4 passos com o passo 1 como próximo (RF-A10, D-55)', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/onboarding')
        ->assertOk()
        ->assertJsonPath('visible', true)
        ->assertJsonPath('completed', false)
        ->assertJsonPath('done_count', 0)
        ->assertJsonPath('total', 4)
        ->assertJsonPath('next', 'company')
        ->assertJsonPath('store_url', 'http://localhost:3000/leiloes-silva')
        ->assertJsonPath('steps.0', ['key' => 'company', 'done' => false, 'available' => true, 'blocked' => false, 'blocked_by' => [], 'missing' => ['email', 'template']])
        ->assertJsonPath('steps.1.key', 'products')
        ->assertJsonPath('steps.1.available', true)
        ->assertJsonPath('steps.2', ['key' => 'payment', 'done' => false, 'available' => true, 'blocked' => false, 'blocked_by' => [], 'missing' => []])
        ->assertJsonPath('steps.3.key', 'auction')
        ->assertJsonPath('steps.3.blocked', true)
        ->assertJsonPath('steps.3.blocked_by', ['products', 'payment']);
});

it('cadastrar o cartão conclui o passo do pagamento e libera o leilão depois do produto (D-55)', function () {
    $auth = liveStore();
    withPaymentCard($auth);

    as_($auth['token'])->getJson('/api/v1/auctioneer/onboarding')
        ->assertJsonPath('steps.2.done', true)
        ->assertJsonPath('steps.3.blocked_by', ['products'])
        ->assertJsonPath('next', 'products');

    createProduct($auth);
    as_($auth['token'])->getJson('/api/v1/auctioneer/onboarding')
        ->assertJsonPath('done_count', 3)
        ->assertJsonPath('next', 'auction')
        ->assertJsonPath('steps.3.blocked', false);
});

it('o passo 1 fica feito com e-mail confirmado, contato e modelo (RF-A11)', function () {
    $auth = registerAuctioneer();
    $this->get(VerifyAuctioneerEmail::url($auth['user']));
    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'watches']);

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/onboarding')
        ->assertJsonPath('steps.0.done', true)
        ->assertJsonPath('steps.0.missing', [])
        ->assertJsonPath('done_count', 1)
        ->assertJsonPath('next', 'products');
});

it('apagar o WhatsApp de contato volta o passo 1 para pendente (RF-A15)', function () {
    $auth = registerAuctioneer(verified: true);
    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'watches']);
    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['contact_phone' => null]);

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/onboarding')
        ->assertJsonPath('steps.0.done', false)
        ->assertJsonPath('steps.0.missing', ['contact_phone']);
});

it('não deixa ocultar antes de concluir', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->postJson('/api/v1/auctioneer/onboarding/dismiss')
        ->assertUnprocessable()
        ->assertJsonPath('errors.onboarding.0', 'Conclua os primeiros passos antes de ocultar esta seção.');
});

it('depois de concluída, pode ser ocultada e não volta (RF-A16)', function () {
    Event::fake([OnboardingCompleted::class]);
    $auth = registerAuctioneer(verified: true);
    withPaymentCard($auth);
    // Aqui a conclusão é simulada pela data gravada.
    AuctioneerRecord::query()->update(['onboarding_completed_at' => now()]);

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/onboarding')
        ->assertJsonPath('completed', true)
        ->assertJsonPath('done_count', 4)
        ->assertJsonPath('next', null);

    asAuctioneer($auth)->postJson('/api/v1/auctioneer/onboarding/dismiss')->assertNoContent();

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/onboarding')->assertJsonPath('visible', false);
});

it('quem concluiu e ocultou antes do passo do pagamento volta a ver a seção até cadastrar o cartão (D-55)', function () {
    $auth = registerAuctioneer(verified: true);
    AuctioneerRecord::query()->update(['onboarding_completed_at' => now(), 'onboarding_dismissed_at' => now()]);

    as_($auth['token'])->getJson('/api/v1/auctioneer/onboarding')
        ->assertJsonPath('visible', true)
        ->assertJsonPath('completed', false)
        ->assertJsonPath('done_count', 3)
        ->assertJsonPath('next', 'payment')
        ->assertJsonPath('steps.2.done', false)
        ->assertJsonPath('steps.3.done', true);

    withPaymentCard($auth);
    as_($auth['token'])->getJson('/api/v1/auctioneer/onboarding')
        ->assertJsonPath('completed', true)
        ->assertJsonPath('visible', false);
});
