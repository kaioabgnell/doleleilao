<?php

declare(strict_types=1);

use DoleLeilao\Auction\Infrastructure\Eloquent\AutoBidRecord;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use Illuminate\Testing\TestResponse;

/*
 * Fase 4: chat do auditório (RF-D03, RF-F05 a RF-F08, RF-F10) e banimento com efeito imediato (RF-G02).
 */

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('UTC')));
});

/** Lote agendado com a configuração de chat pedida. @return array{auth: array<string, mixed>, auction: array<string, mixed>} */
function chatAuction(array $chat = []): array
{
    $auth = liveStore();
    withPaymentCard($auth);
    $p = createProduct($auth);
    $auction = draftAuction($auth, [$p], extra: ['settings' => ['chat_visibility' => 'everyone', 'chat_write' => 'logged_in', ...$chat]]);
    as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/schedule")->assertOk();

    return ['auth' => $auth, 'auction' => $auction];
}

function say(string $token, string $text, ?string $replyTo = null): TestResponse
{
    return as_($token)->postJson('/api/v1/bidders/me/auctions/relogios-de-colecao/chat', array_filter(['body' => $text, 'reply_to_id' => $replyTo]));
}

function history(?string $token = null): TestResponse
{
    app('auth')->forgetGuards();
    $request = $token === null ? test()->withoutToken() : test()->withToken($token);

    return $request->getJson('/api/v1/storefronts/leiloes-silva/auctions/relogios-de-colecao/chat');
}

describe('mensagens (RF-F05, RF-F06)', function () {
    it('participante escreve e todos leem, com apelido e avatar', function () {
        chatAuction();
        $ana = eligibleBidder(1);

        say($ana['token'], '  Boa noite, pessoal!  ')->assertCreated()
            ->assertJsonPath('body', 'Boa noite, pessoal!')
            ->assertJsonPath('author.type', 'bidder')
            ->assertJsonPath('author.nickname', 'Pessoa1 T.')
            ->assertJsonPath('reply_to', null);

        history()->assertOk()
            ->assertJsonPath('data.0.body', 'Boa noite, pessoal!')
            ->assertJsonPath('access.can_read', true)
            ->assertJsonPath('access.can_write', false)
            ->assertJsonPath('access.reason', 'login')
            ->assertJsonPath('access.channel.private', false);
    });

    it('responde citando a original', function () {
        chatAuction();
        $ana = eligibleBidder(1);
        $bruno = eligibleBidder(2);
        $original = say($ana['token'], 'Alguém sabe se o Rolex tem caixa?')->json('id');
        $this->travel(2)->seconds();

        say($bruno['token'], 'Tem sim, está na descrição.', $original)->assertCreated()
            ->assertJsonPath('reply_to.id', $original)
            ->assertJsonPath('reply_to.author', 'Pessoa1 T.')
            ->assertJsonPath('reply_to.excerpt', 'Alguém sabe se o Rolex tem caixa?');
    });

    it('o leiloeiro escreve com o nome da loja', function () {
        ['auth' => $auth, 'auction' => $auction] = chatAuction();

        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/chat", ['body' => 'Começamos em 5 minutos!'])
            ->assertCreated()
            ->assertJsonPath('author.type', 'auctioneer')
            ->assertJsonPath('author.nickname', 'Leilões Silva');
    });

    it('recusa vazia, acima de 500 caracteres e resposta a mensagem inexistente', function () {
        chatAuction();
        $ana = eligibleBidder(1);

        say($ana['token'], '   ')->assertUnprocessable();
        say($ana['token'], str_repeat('a', 501))->assertUnprocessable()->assertJsonPath('errors.body.0', 'A mensagem pode ter até 500 caracteres.');
        say($ana['token'], 'oi', '01aaaaaaaaaaaaaaaaaaaaaaaa')->assertUnprocessable()->assertJsonPath('errors.reply_to_id.0', 'A mensagem que você quer responder não existe mais.');
    });

    it('limita 1 mensagem por segundo e 20 por minuto', function () {
        chatAuction();
        $ana = eligibleBidder(1);

        say($ana['token'], 'um')->assertCreated();
        say($ana['token'], 'dois')->assertStatus(429)->assertJsonPath('detail', 'Calma! Espere um pouco para mandar outra mensagem.');

        for ($i = 0; $i < 19; $i++) {
            $this->travel(1)->seconds();
            say($ana['token'], "msg {$i}")->assertCreated();
        }
        $this->travel(1)->seconds();
        say($ana['token'], 'vinte e um')->assertStatus(429);
    });

    it('pagina o histórico do mais antigo para o mais novo', function () {
        chatAuction();
        $ana = eligibleBidder(1);
        foreach (range(1, 55) as $i) {
            $this->travel(4)->seconds();
            say($ana['token'], "mensagem {$i}")->assertCreated();
        }

        $first = history()->assertJsonPath('has_more', true)->json();
        expect($first['data'])->toHaveCount(50)
            ->and($first['data'][0]['body'])->toBe('mensagem 6')
            ->and($first['data'][49]['body'])->toBe('mensagem 55');

        $older = $this->getJson('/api/v1/storefronts/leiloes-silva/auctions/relogios-de-colecao/chat?before='.$first['data'][0]['id'])->json();
        expect(array_column($older['data'], 'body'))->toBe(['mensagem 1', 'mensagem 2', 'mensagem 3', 'mensagem 4', 'mensagem 5'])
            ->and($older['has_more'])->toBeFalse();
    });
});

describe('quem vê e quem escreve (RF-D03, RF-F10)', function () {
    it('chat só para logados: visitante não recebe as mensagens', function () {
        chatAuction(['chat_visibility' => 'logged_in']);
        $ana = eligibleBidder(1);
        say($ana['token'], 'Oi')->assertCreated();

        history()->assertJsonPath('data', [])->assertJsonPath('access.can_read', false);
        history($ana['token'])->assertJsonPath('data.0.body', 'Oi')->assertJsonPath('access.channel.private', true);
    });

    it('escrita só com cadastro completo', function () {
        chatAuction(['chat_write' => 'eligible']);
        $bia = eligibleBidder(3, verified: false);

        say($bia['token'], 'Oi')->assertForbidden()->assertJsonPath('reason', 'incomplete');
        history($bia['token'])->assertJsonPath('access.message', 'Complete seu cadastro para escrever no chat.');
    });

    it('chat desligado não mostra nada', function () {
        chatAuction(['chat_enabled' => false]);
        $ana = eligibleBidder(1);

        say($ana['token'], 'Oi')->assertForbidden()->assertJsonPath('reason', 'disabled');
        history($ana['token'])->assertJsonPath('access.enabled', false)->assertJsonPath('access.can_read', false);
    });

    it('depois do leilão o chat fica só para leitura', function () {
        ['auth' => $auth, 'auction' => $auction] = chatAuction();
        $ana = eligibleBidder(1);
        say($ana['token'], 'Boa sorte!')->assertCreated();
        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/start")->assertOk();
        tick();
        as_($auth['token'])->postJson("/api/v1/auctioneer/items/{$auction['items'][0]['id']}/skip")->assertOk();
        tick();

        $this->travel(5)->seconds();
        say($ana['token'], 'Já acabou?')->assertForbidden()->assertJsonPath('reason', 'closed');
        history($ana['token'])->assertJsonPath('data.0.body', 'Boa sorte!');
    });

    it('o histórico fica na loja por 12 horas depois do fim; o painel continua vendo', function () {
        ['auth' => $auth, 'auction' => $auction] = chatAuction();
        $ana = eligibleBidder(1);
        say($ana['token'], 'Boa sorte!')->assertCreated();
        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/start")->assertOk();
        tick();
        as_($auth['token'])->postJson("/api/v1/auctioneer/items/{$auction['items'][0]['id']}/skip")->assertOk();
        tick();

        $this->travel(11)->hours();
        history($ana['token'])->assertJsonPath('data.0.body', 'Boa sorte!')->assertJsonPath('access.can_read', true);

        $this->travel(1)->hours();
        history($ana['token'])->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('access.can_read', false)
            ->assertJsonPath('access.reason', 'expired')
            ->assertJsonPath('access.message', 'O chat deste leilão ficou disponível por 12 horas depois do fim.');
        history()->assertJsonCount(0, 'data');

        app('auth')->forgetGuards();
        as_($auth['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}/chat")->assertJsonPath('data.0.body', 'Boa sorte!');
    });
});

describe('moderação (RF-F08)', function () {
    it('apagar esconde o texto, inclusive na citação', function () {
        ['auth' => $auth, 'auction' => $auction] = chatAuction();
        $ana = eligibleBidder(1);
        $bruno = eligibleBidder(2);
        $spam = say($ana['token'], 'Compre no meu site!')->json('id');
        $this->travel(2)->seconds();
        say($bruno['token'], 'Que isso?', $spam)->assertCreated();

        as_($auth['token'])->deleteJson("/api/v1/auctioneer/auctions/{$auction['id']}/chat/{$spam}")->assertNoContent();
        as_($auth['token'])->deleteJson("/api/v1/auctioneer/auctions/{$auction['id']}/chat/{$spam}")->assertUnprocessable();

        $data = history()->json('data');
        expect($data[0])->toMatchArray(['id' => $spam, 'deleted' => true, 'body' => null])
            ->and($data[1]['reply_to'])->toMatchArray(['id' => $spam, 'deleted' => true, 'excerpt' => null]);
        $this->travel(2)->seconds();
        say($bruno['token'], 'resposta', $spam)->assertUnprocessable();
    });

    it('silenciar por 15 minutos e até o fim do leilão; liberar', function () {
        ['auth' => $auth, 'auction' => $auction] = chatAuction();
        $ana = eligibleBidder(1);
        $panel = fn () => as_($auth['token']);

        $panel()->putJson("/api/v1/auctioneer/auctions/{$auction['id']}/mutes/{$ana['id']}", ['minutes' => 7])->assertUnprocessable();
        $panel()->putJson("/api/v1/auctioneer/auctions/{$auction['id']}/mutes/{$ana['id']}", ['minutes' => 15])
            ->assertOk()->assertJsonPath('until', '2026-11-20T12:15:00.000Z');

        say($ana['token'], 'Oi')->assertForbidden()->assertJsonPath('detail', 'O leiloeiro silenciou você neste leilão.');
        history($ana['token'])->assertJsonPath('access.muted_until', '2026-11-20T12:15:00.000Z');
        $panel()->getJson("/api/v1/auctioneer/auctions/{$auction['id']}/chat")->assertJsonPath("muted.{$ana['id']}", '2026-11-20T12:15:00.000Z');

        $this->travel(15)->minutes();
        say($ana['token'], 'Voltei')->assertCreated();

        $panel()->putJson("/api/v1/auctioneer/auctions/{$auction['id']}/mutes/{$ana['id']}", ['minutes' => null])->assertOk()->assertJsonPath('until', null);
        $this->travel(10)->hours();
        say($ana['token'], 'Ainda?')->assertForbidden();

        $panel()->deleteJson("/api/v1/auctioneer/auctions/{$auction['id']}/mutes/{$ana['id']}")->assertNoContent();
        say($ana['token'], 'Liberado')->assertCreated();
    });

    it('mostra os nomes completos só no painel', function () {
        ['auth' => $auth, 'auction' => $auction] = chatAuction();
        $ana = eligibleBidder(1);
        say($ana['token'], 'Oi')->assertCreated();

        history()->assertJsonMissingPath('data.0.author.name');
        as_($auth['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}/chat")->assertJsonPath('data.0.author.name', 'Pessoa1 Teste');
    });

    it('leiloeiro de outra loja não modera', function () {
        ['auction' => $auction] = chatAuction();
        $ana = eligibleBidder(1);
        $other = liveStore(['email' => 'outro@x.com', 'slug' => 'outra-loja', 'document' => '04.252.011/0001-10', 'company_name' => 'Outra Loja']);

        as_($other['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}/chat")->assertNotFound();
        as_($other['token'])->putJson("/api/v1/auctioneer/bidders/{$ana['id']}/ban", ['reason' => 'teste'])->assertNotFound();
    });
});

describe('banimento com efeito imediato (RF-G02)', function () {
    it('derruba a sessão, desliga os automáticos, bloqueia lance, chat e login; desbanir libera o login', function () {
        ['auth' => $auth, 'auction' => $auction] = chatAuction();
        $ana = eligibleBidder(1);
        as_($ana['token'])->putJson("/api/v1/items/{$auction['items'][0]['id']}/auto-bid", ['max_amount_cents' => 500000])->assertOk();

        as_($auth['token'])->putJson("/api/v1/auctioneer/bidders/{$ana['id']}/ban", ['reason' => 'x'])->assertUnprocessable();
        as_($auth['token'])->putJson("/api/v1/auctioneer/bidders/{$ana['id']}/ban", ['reason' => 'Ofensas no chat'])->assertNoContent();

        say($ana['token'], 'Oi')->assertUnauthorized();
        expect(AutoBidRecord::query()->withoutGlobalScope('tenant')->where('active', true)->count())->toBe(0)
            ->and(BidderUser::query()->where('public_id', $ana['id'])->firstOrFail()->tokens()->count())->toBe(0);

        app('auth')->forgetGuards();
        $login = fn () => $this->withoutToken()->postJson('/api/v1/storefronts/leiloes-silva/sessions', ['email' => 'pessoa1@exemplo.com', 'password' => 'senha-forte-1']);
        $login()->assertForbidden()->assertJsonPath('reason', 'banned');

        as_($auth['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}/chat")->assertJsonPath('banned', [$ana['id']]);
        as_($auth['token'])->deleteJson("/api/v1/auctioneer/bidders/{$ana['id']}/ban")->assertNoContent();
        app('auth')->forgetGuards();
        $login()->assertOk();
    });
});

describe('WebSocket do chat', function () {
    it('canal privado do chat: participante da loja e leiloeiro entram; chat público não usa canal privado', function () {
        ['auth' => $auth, 'auction' => $auction] = chatAuction(['chat_visibility' => 'logged_in']);
        $ana = eligibleBidder(1);
        config(['broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's']);
        $channel = "private-auction.{$auction['id']}.chat";

        as_($ana['token'])->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => $channel])
            ->assertOk()->assertJsonPath('auth', 'k:'.hash_hmac('sha256', "1.2:{$channel}", 's'));
        as_($auth['token'])->postJson('/api/v1/auctioneer/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => $channel])->assertOk();
        as_($auth['token'])->postJson('/api/v1/auctioneer/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-bidder.x'])->assertForbidden();
    });
});
