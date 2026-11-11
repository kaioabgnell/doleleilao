<?php

declare(strict_types=1);

use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionItemRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuditLogRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AwardRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\BidRecord;
use DoleLeilao\Auction\Infrastructure\RealtimeHealth;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Notification\Infrastructure\NotificationRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Fase 3: lotes, lances, relógio e controles (specs/03-dominio.md#regras-de-lance, RF-D01 a RF-D06,
 * RF-F01 a RF-F03, RF-E10, RF-N03 a RF-N05).
 */

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('UTC')));
});

function productStatus(string $publicId): string
{
    return ProductRecord::query()->withoutGlobalScope('tenant')->where('public_id', $publicId)->firstOrFail()->status->value;
}

function itemStatus(string $publicId): string
{
    return AuctionItemRecord::query()->withoutGlobalScope('tenant')->where('public_id', $publicId)->firstOrFail()->status->value;
}

describe('cadastro do lote no painel (RF-D01, RF-D02)', function () {
    it('cria rascunho com itens que copiam valor inicial e incremento do produto', function () {
        $auth = liveStore();
        $p = createProduct($auth);
        $auction = draftAuction($auth, [$p]);

        expect($auction['status'])->toBe('draft')
            ->and($auction['slug'])->toBe('relogios-de-colecao')
            ->and($auction['starts_at'])->toBe('2026-11-21T23:00:00.000Z')
            ->and($auction['items'])->toHaveCount(1)
            ->and($auction['items'][0]['starting_price_cents'])->toBe(150000)
            ->and($auction['items'][0]['increment_cents'])->toBe(10000)
            ->and($auction['settings']['item_duration_s'])->toBe(60);
    });

    it('ajusta valores dos itens e a ordem até o pregão', function () {
        $auth = liveStore();
        $p1 = createProduct($auth, ['title' => 'Primeiro']);
        $p2 = createProduct($auth, ['title' => 'Segundo']);
        $auction = draftAuction($auth, [$p1, $p2]);
        $firstItemId = $auction['items'][0]['id'];

        $updated = as_($auth['token'])->patchJson("/api/v1/auctioneer/auctions/{$auction['id']}", [
            'items' => [
                ['product_id' => $p2['id']],
                ['product_id' => $p1['id'], 'starting_price_cents' => 99000, 'increment_cents' => 2000],
            ],
        ])->assertOk()->json();

        expect(array_column($updated['items'], 'title'))->toBe(['Segundo', 'Primeiro'])
            ->and($updated['items'][1]['id'])->toBe($firstItemId)
            ->and($updated['items'][1]['starting_price_cents'])->toBe(99000);
    });

    it('recusa data no passado, rascunho de produto e produto de outro lote', function () {
        $auth = liveStore();
        $draft = createProduct($auth, ['title' => 'Rascunho'], available: false);
        $ok = createProduct($auth, ['title' => 'Livre']);
        draftAuction($auth, [$ok]);

        as_($auth['token'])->postJson('/api/v1/auctioneer/auctions', [
            'name' => 'Outro', 'starts_at' => '2026-11-19T20:00:00-03:00',
        ])->assertUnprocessable()->assertJsonPath('errors.starts_at.0', 'A data de início precisa estar no futuro.');

        as_($auth['token'])->postJson('/api/v1/auctioneer/auctions', [
            'name' => 'Outro', 'starts_at' => '2026-11-22T20:00:00-03:00',
            'items' => [['product_id' => $draft['id']], ['product_id' => $ok['id']]],
        ])->assertUnprocessable()
            ->assertJsonPath('errors', [
                'items.0.product_id' => ['"Rascunho" não está disponível. Deixe o produto como disponível no catálogo.'],
                'items.1.product_id' => ['"Livre" já está em outro lote.'],
            ]);
    });

    it('capa própria substitui a foto do 1º item na loja e pode ser removida', function () {
        Storage::fake('public');
        ['auth' => $auth, 'auction' => $auction] = scheduledAuction();
        $store = fn () => test()->withoutToken()->getJson('/api/v1/storefronts/leiloes-silva/auctions')->json('data.0.cover');

        $withCover = as_($auth['token'])->post("/api/v1/auctioneer/auctions/{$auction['id']}/cover", ['cover' => UploadedFile::fake()->image('capa.jpg', 2400, 1200)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('cover.custom', true)
            ->json('cover');
        expect($withCover['card']['webp'])->toEndWith('.webp')
            ->and($withCover['thumb'])->not->toBe($withCover['card']['webp'])
            ->and($store())->toMatchArray(['custom' => true, 'thumb' => $withCover['thumb']]);
        $path = AuctionRecord::query()->withoutGlobalScope('tenant')->value('cover_path');
        Storage::disk('public')->assertExists($path);

        as_($auth['token'])->deleteJson("/api/v1/auctioneer/auctions/{$auction['id']}/cover")
            ->assertOk()
            ->assertJsonPath('cover.custom', false);
        Storage::disk('public')->assertMissing($path);
        expect($store()['custom'])->toBeFalse();
    });

    it('capa recusa arquivo que não é imagem', function () {
        Storage::fake('public');
        $auth = liveStore();
        $auction = draftAuction($auth, [createProduct($auth)]);

        as_($auth['token'])->post("/api/v1/auctioneer/auctions/{$auction['id']}/cover", ['cover' => UploadedFile::fake()->create('capa.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['cover']]);
    });

    it('incremento abaixo de R$ 1,00 é recusado', function () {
        $auth = liveStore();
        $p = createProduct($auth);
        as_($auth['token'])->postJson('/api/v1/auctioneer/auctions', [
            'name' => 'Lote', 'starts_at' => '2026-11-22T20:00:00-03:00',
            'items' => [['product_id' => $p['id'], 'increment_cents' => 50]],
        ])->assertUnprocessable()->assertJsonPath('errors', ['items.0.increment_cents' => ['O incremento mínimo é R$ 1,00.']]);
    });

    it('agendar exige item, marca os produtos como em leilão e conclui o último passo', function () {
        $auth = liveStore();
        withPaymentCard($auth);
        $empty = draftAuction($auth, []);
        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$empty['id']}/schedule")
            ->assertUnprocessable()->assertJsonPath('errors.items.0', 'Adicione pelo menos 1 item antes de agendar.');

        $p = createProduct($auth);
        $auction = draftAuction($auth, [$p], extra: ['name' => 'Lote de Natal']);
        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/schedule")->assertOk()->assertJsonPath('status', 'scheduled');

        expect(productStatus($p['id']))->toBe('in_auction');
        as_($auth['token'])->getJson('/api/v1/auctioneer/onboarding')
            ->assertJsonPath('completed', true)
            ->assertJsonPath('steps.3.key', 'auction')
            ->assertJsonPath('steps.3.done', true);
    });

    it('voltar para rascunho ou cancelar devolve os produtos ao catálogo', function () {
        ['auth' => $auth, 'auction' => $auction, 'products' => $products] = scheduledAuction();

        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        expect(array_values(array_unique(array_map(fn ($p) => productStatus($p['id']), $products))))->toBe(['available']);

        as_($auth['token'])->deleteJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertNoContent();
    });

    it('lote de uma loja não aparece no painel de outra', function () {
        ['auction' => $auction] = scheduledAuction();
        $other = liveStore(['email' => 'outro@x.com', 'slug' => 'outra-loja', 'document' => '04.252.011/0001-10', 'company_name' => 'Outra Loja']);

        as_($other['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertNotFound();
        as_($other['token'])->getJson('/api/v1/auctioneer/auctions')->assertJsonCount(0, 'data');
    });
});

/** Lote encerrado: o 1º item arrematado pela Ana, o 2º pulado. */
function finishedAuction(): array
{
    $data = scheduledAuction();
    ['auth' => $auth, 'auction' => $auction, 'items' => $items] = $data;
    $ana = eligibleBidder(1);
    as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/start")->assertOk();
    tick();
    bid($ana['token'], $items[0]['id'], 150000)->assertCreated();
    as_($auth['token'])->postJson("/api/v1/auctioneer/items/{$items[0]['id']}/close")->assertOk();
    test()->travel(10)->seconds();
    tick();
    as_($auth['token'])->postJson("/api/v1/auctioneer/items/{$items[1]['id']}/skip")->assertOk();
    tick();
    as_($auth['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertJsonPath('status', 'finished');

    return $data;
}

describe('lote encerrado no painel', function () {
    it('esconde e volta a mostrar o lote na loja', function () {
        ['auth' => $auth, 'auction' => $auction] = finishedAuction();
        $store = fn () => test()->withoutToken()->getJson('/api/v1/storefronts/leiloes-silva/auctions?status=finished');

        as_($auth['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertJsonPath('visible_in_store', true);
        $store()->assertJsonCount(1, 'data');

        as_($auth['token'])->putJson("/api/v1/auctioneer/auctions/{$auction['id']}/visibility", ['visible' => false])
            ->assertOk()->assertJsonPath('visible_in_store', false);
        $store()->assertJsonCount(0, 'data');
        test()->withoutToken()->getJson("/api/v1/storefronts/leiloes-silva/auctions/{$auction['slug']}")->assertNotFound();

        as_($auth['token'])->putJson("/api/v1/auctioneer/auctions/{$auction['id']}/visibility", ['visible' => true])
            ->assertOk()->assertJsonPath('visible_in_store', true);
        $store()->assertJsonCount(1, 'data');
    });

    it('agendado não pode ser escondido nem apagado', function () {
        ['auth' => $auth, 'auction' => $auction] = scheduledAuction();

        as_($auth['token'])->putJson("/api/v1/auctioneer/auctions/{$auction['id']}/visibility", ['visible' => false])->assertUnprocessable();
        as_($auth['token'])->deleteJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertUnprocessable();
    });

    it('apaga o lote encerrado com itens, lances e arrematações', function () {
        ['auth' => $auth, 'auction' => $auction] = finishedAuction();
        expect(AwardRecord::query()->withoutGlobalScope('tenant')->count())->toBe(1);

        as_($auth['token'])->deleteJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertNoContent();

        as_($auth['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertNotFound();
        expect(AuctionItemRecord::query()->withoutGlobalScope('tenant')->count())->toBe(0)
            ->and(BidRecord::query()->withoutGlobalScope('tenant')->count())->toBe(0)
            ->and(AwardRecord::query()->withoutGlobalScope('tenant')->count())->toBe(0);
    });
});

describe('loja pública (RF-E05)', function () {
    it('lista agendados e esconde rascunhos', function () {
        ['auth' => $auth] = scheduledAuction();
        $p = createProduct($auth, ['title' => 'Ainda rascunho']);
        draftAuction($auth, [$p], extra: ['name' => 'Rascunho secreto']);

        $this->withoutToken()->getJson('/api/v1/storefronts/leiloes-silva/auctions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Relógios de Coleção')
            ->assertJsonPath('data.0.items_count', 2)
            ->assertJsonMissingPath('data.0.settings.item_duration_s');

        $this->getJson('/api/v1/storefronts/leiloes-silva/auctions/rascunho-secreto')->assertNotFound();
    });

    it('mostra o leilão pelo endereço, com itens, produtos e o horário do servidor', function () {
        scheduledAuction();

        $this->withoutToken()->getJson('/api/v1/storefronts/leiloes-silva/auctions/relogios-de-colecao')
            ->assertOk()
            ->assertJsonPath('auction.status', 'scheduled')
            ->assertJsonPath('auction.items.0.product.title', 'Rolex Datejust 1985')
            ->assertJsonPath('auction.items.1.next_minimum_cents', 80000)
            ->assertJsonPath('server_now', '2026-11-20T12:00:00.000Z');
    });
});

describe('pregão de ponta a ponta (regras 1 a 8)', function () {
    it('o relógio inicia, os lances disputam, o item fecha vendido e o próximo abre depois do intervalo', function () {
        ['auth' => $auth, 'auction' => $auction, 'items' => $items, 'products' => $products] = scheduledAuction();
        $ana = eligibleBidder(1);
        $bruno = eligibleBidder(2);

        // Antes da hora: nada acontece.
        tick();
        bid($ana['token'], $items[0]['id'], 150000)->assertUnprocessable()->assertJsonPath('detail', 'Este item não está em pregão.');

        // Na hora marcada: o lote começa e o primeiro item abre.
        $this->travelTo(new DateTimeImmutable('2026-11-21 23:00:00', new DateTimeZone('UTC')));
        tick();
        $state = $this->withoutToken()->getJson("/api/v1/storefronts/leiloes-silva/auctions/{$auction['slug']}/state")->assertOk();
        $state->assertJsonPath('status', 'live')
            ->assertJsonPath('current_item_id', $items[0]['id'])
            ->assertJsonPath('items.0.status', 'open')
            ->assertJsonPath('items.0.ends_at', '2026-11-21T23:01:00.000Z');

        // Ana dá o lance inicial; o clique duplo devolve o mesmo lance.
        $first = bid($ana['token'], $items[0]['id'], 150000, 'clique-ana-0001')->assertCreated();
        $first->assertJsonPath('item.is_leading', true)->assertJsonPath('item.next_minimum_cents', 160000);
        bid($ana['token'], $items[0]['id'], 150000, 'clique-ana-0001')->assertOk()->assertJsonPath('replayed', true)
            ->assertJsonPath('bid_id', $first->json('bid_id'));

        // Bruno cadastra automático; cobre a Ana na hora.
        as_($bruno['token'])->putJson("/api/v1/items/{$items[0]['id']}/auto-bid", ['max_amount_cents' => 200000])
            ->assertOk()->assertJsonPath('item.is_leading', true)->assertJsonPath('item.current_price_cents', 160000);

        // Ana tenta abaixo do mínimo e recebe o novo mínimo; depois dá 180.000 e o automático cobre.
        bid($ana['token'], $items[0]['id'], 165000)->assertUnprocessable()->assertJsonPath('minimum_cents', 170000);
        $this->travelTo(new DateTimeImmutable('2026-11-21 23:00:50', new DateTimeZone('UTC')));
        bid($ana['token'], $items[0]['id'], 180000)->assertCreated()->assertJsonPath('item.is_leading', false)
            ->assertJsonPath('item.current_price_cents', 190000)
            ->assertJsonPath('item.ends_at', '2026-11-21T23:01:05.000Z'); // prorrogado

        expect(BidRecord::query()->withoutGlobalScope('tenant')->count())->toBe(4);

        // Tempo esgotado: o relógio fecha vendido para o Bruno e cria a arrematação.
        $this->travelTo(new DateTimeImmutable('2026-11-21 23:01:05', new DateTimeZone('UTC')));
        tick();
        $item = AuctionItemRecord::query()->withoutGlobalScope('tenant')->where('public_id', $items[0]['id'])->firstOrFail();
        expect($item->status->value)->toBe('closed_sold')
            ->and(AwardRecord::query()->withoutGlobalScope('tenant')->count())->toBe(1)
            ->and(productStatus($products[0]['id']))->toBe('sold');

        // Aviso do vencedor no sino (RF-N03) e do leiloeiro (RF-N04).
        $brunoId = BidderUser::query()->where('public_id', $bruno['id'])->value('id');
        expect(NotificationRecord::query()->withoutGlobalScope('tenant')->where('recipient_type', 'bidder')->where('recipient_id', $brunoId)->where('kind', 'auction_won')->value('body'))
            ->toBe('Em 21/11/2026 você arrematou Rolex Datejust 1985 por R$ 1.900,00.')
            ->and(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'item_sold')->value('title'))
            ->toBe('Rolex Datejust 1985 foi arrematado por R$ 1.900,00');

        // Intervalo de 10 s antes do próximo item.
        $this->travelTo(new DateTimeImmutable('2026-11-21 23:01:14', new DateTimeZone('UTC')));
        tick();
        expect(itemStatus($items[1]['id']))->toBe('queued');
        $this->travelTo(new DateTimeImmutable('2026-11-21 23:01:15', new DateTimeZone('UTC')));
        tick();
        expect(itemStatus($items[1]['id']))->toBe('open');

        // Sem lances: fecha sem venda e o lote termina.
        $this->travelTo(new DateTimeImmutable('2026-11-21 23:02:15', new DateTimeZone('UTC')));
        tick();
        tick();
        expect(itemStatus($items[1]['id']))->toBe('closed_unsold')
            ->and(productStatus($products[1]['id']))->toBe('unsold');
        as_($auth['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertJsonPath('status', 'finished');
    });

    it('quem não tem cadastro completo ou está banido não dá lance', function () {
        ['auth' => $auth, 'items' => $items] = scheduledAuction();
        as_($auth['token'])->postJson('/api/v1/auctioneer/auctions/'.scheduledAuctionId().'/start')->assertOk();
        $incomplete = eligibleBidder(3, verified: false);
        $banned = eligibleBidder(4);
        $bannedId = BidderUser::query()->where('public_id', $banned['id'])->value('id');
        DB::table('bans')->insert(['auctioneer_id' => $auth['user']->auctioneer_id, 'bidder_id' => $bannedId, 'banned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        tick();

        bid($incomplete['token'], $items[0]['id'], 150000)->assertForbidden()->assertJsonPath('reason', 'incomplete');
        bid($banned['token'], $items[0]['id'], 150000)->assertForbidden()->assertJsonPath('reason', 'banned');
        as_($incomplete['token'])->postJson("/api/v1/items/{$items[0]['id']}/bids", ['amount_cents' => 150000])->assertStatus(400);
    });

    it('participante de outra loja não enxerga o item', function () {
        ['items' => $items] = scheduledAuction();
        liveStore(['email' => 'outro@x.com', 'slug' => 'outra-loja', 'document' => '04.252.011/0001-10', 'company_name' => 'Outra Loja']);
        $stranger = eligibleBidder(9, 'outra-loja');

        bid($stranger['token'], $items[0]['id'], 150000)->assertNotFound();
    });
});

function scheduledAuctionId(): string
{
    return (string) DB::table('auctions')->value('public_id');
}

describe('controles do pregão (RF-D05)', function () {
    it('pausa, retoma, anula o último lance, pula e encerra, tudo no log', function () {
        ['auth' => $auth, 'auction' => $auction, 'items' => $items] = scheduledAuction();
        $ana = eligibleBidder(1);
        $bruno = eligibleBidder(2);
        $panel = fn () => as_($auth['token']);

        $panel()->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/start")->assertOk()->assertJsonPath('auction.status', 'live');
        tick();
        bid($ana['token'], $items[0]['id'], 150000)->assertCreated();
        bid($bruno['token'], $items[0]['id'], 200000)->assertCreated();

        $this->travel(20)->seconds();
        $panel()->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/pause")->assertOk()->assertJsonPath('auction.items.0.status', 'paused')
            ->assertJsonPath('auction.items.0.paused_remaining_ms', 40000);
        bid($ana['token'], $items[0]['id'], 300000)->assertUnprocessable()->assertJsonPath('detail', 'O pregão está pausado. Aguarde o leiloeiro retomar.');

        $this->travel(5)->minutes();
        $panel()->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/resume")->assertOk()->assertJsonPath('auction.items.0.status', 'open');

        $panel()->postJson("/api/v1/auctioneer/items/{$items[0]['id']}/void-last-bid", ['reason' => 'x'])->assertUnprocessable();
        $live = $panel()->postJson("/api/v1/auctioneer/items/{$items[0]['id']}/void-last-bid", ['reason' => 'Lance por engano'])->assertOk();
        $live->assertJsonPath('auction.items.0.current_price_cents', 150000)
            ->assertJsonPath('recent_bids.0.bidder.name', 'Pessoa1 Teste');
        expect(BidRecord::query()->withoutGlobalScope('tenant')->whereNotNull('voided_at')->value('void_reason'))->toBe('Lance por engano');

        $panel()->postJson("/api/v1/auctioneer/items/{$items[0]['id']}/close")->assertOk()->assertJsonPath('auction.items.0.status', 'closed_sold');
        expect(AwardRecord::query()->withoutGlobalScope('tenant')->value('amount'))->toBe(150000);

        $this->travel(10)->seconds();
        tick();
        $panel()->postJson("/api/v1/auctioneer/items/{$items[1]['id']}/skip")->assertOk()->assertJsonPath('auction.items.1.status', 'skipped');
        tick();

        $log = $panel()->getJson("/api/v1/auctioneer/auctions/{$auction['id']}/live")->assertOk()->json('log');
        expect(array_column($log, 'action'))->toBe([
            'auction_finished', 'item_skipped', 'item_opened', 'item_closed_manually', 'bid_voided',
            'item_resumed', 'item_paused', 'item_opened', 'auction_started',
        ])->and(AuditLogRecord::query()->withoutGlobalScope('tenant')->where('action', 'item_paused')->value('actor_type'))->toBe('auctioneer_user');
    });

    it('depois de iniciado, o cadastro do lote não muda', function () {
        ['auth' => $auth, 'auction' => $auction] = scheduledAuction();
        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/start")->assertOk();

        as_($auth['token'])->patchJson("/api/v1/auctioneer/auctions/{$auction['id']}", ['name' => 'Novo nome'])
            ->assertUnprocessable()->assertJsonPath('detail', 'Este lote já começou. Use os controles do pregão.');
    });
});

describe('"Quero participar" e avisos (RF-E10, RF-N03 a RF-N05)', function () {
    it('participante completo recebe "habilitado" e o leiloeiro vê o novo participante', function () {
        scheduledAuction();
        $ana = eligibleBidder(1);

        as_($ana['token'])->putJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertCreated()
            ->assertJsonPath('participating', true)->assertJsonPath('eligible', true);
        as_($ana['token'])->putJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertOk();

        expect(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'participation_enabled')->count())->toBe(1)
            ->and(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'participation_enabled')->value('title'))
            ->toBe('Seu cadastro está habilitado para participar do leilão Relógios de Coleção')
            ->and(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'new_participant')->value('body'))->toBe('Pessoa1 T. vai participar.');
    });

    it('com cadastro incompleto, o "habilitado" chega quando ele completa', function () {
        scheduledAuction();
        $bia = eligibleBidder(5, verified: false);
        as_($bia['token'])->putJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertCreated()
            ->assertJsonPath('eligible', false)->assertJsonPath('missing', ['email']);
        expect(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'participation_enabled')->count())->toBe(0);

        BidderUser::query()->where('public_id', $bia['id'])->firstOrFail()->markEmailVerified();
        expect(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'participation_enabled')->count())->toBe(1);
    });

    it('avisa amanhã e hoje às 8h, sem repetir', function () {
        scheduledAuction(); // amanhã às 20h (Brasília)
        $ana = eligibleBidder(1);
        as_($ana['token'])->putJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertCreated();

        $this->artisan('auction:send-reminders')->assertSuccessful();
        $this->artisan('auction:send-reminders')->assertSuccessful();
        expect(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'auction_tomorrow')->count())->toBe(2); // participante + leiloeiro
        expect(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'auction_tomorrow')->where('recipient_type', 'bidder')->value('title'))
            ->toBe('O leilão Relógios de Coleção é amanhã');

        $this->travelTo(new DateTimeImmutable('2026-11-21 11:00:00', new DateTimeZone('UTC'))); // 8h em Brasília
        $this->artisan('auction:send-reminders')->assertSuccessful();
        expect(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'auction_today')->where('recipient_type', 'bidder')->value('body'))
            ->toBe('Começa hoje às 20h. Você marcou que vai participar.');
    });

    it('quem marca no dia, depois das 8h, recebe o "hoje" na hora', function () {
        scheduledAuction();
        $this->travelTo(new DateTimeImmutable('2026-11-21 15:00:00', new DateTimeZone('UTC')));
        $ana = eligibleBidder(1);
        as_($ana['token'])->putJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertCreated();

        expect(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'auction_today')->count())->toBe(1);
    });

    it('dá para desmarcar até o leilão começar', function () {
        ['auth' => $auth, 'auction' => $auction] = scheduledAuction();
        $ana = eligibleBidder(1);
        as_($ana['token'])->putJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertCreated();
        as_($ana['token'])->deleteJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertOk()->assertJsonPath('participating', false);

        as_($ana['token'])->putJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertCreated();
        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/start")->assertOk();
        as_($ana['token'])->deleteJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertConflict();
    });
});

describe('WebSocket (canal privado do participante)', function () {
    it('autoriza só o próprio canal', function () {
        scheduledAuction();
        $ana = eligibleBidder(1);
        config(['broadcasting.connections.reverb.key' => 'chave', 'broadcasting.connections.reverb.secret' => 'segredo']);

        $auth = as_($ana['token'])->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => "private-bidder.{$ana['id']}"])
            ->assertOk()->json('auth');
        expect($auth)->toBe('chave:'.hash_hmac('sha256', "123.456:private-bidder.{$ana['id']}", 'segredo'));

        as_($ana['token'])->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-bidder.outro'])->assertForbidden();
    });

    it('a vigilância do WebSocket aceita a porta do .env (texto) e não derruba o relógio', function () {
        config(['broadcasting.default' => 'reverb', 'reverb.servers.reverb.hostname' => '127.0.0.1', 'reverb.servers.reverb.port' => '1']);

        expect(RealtimeHealth::reachable())->toBeFalse();
        tick();
    });
});
