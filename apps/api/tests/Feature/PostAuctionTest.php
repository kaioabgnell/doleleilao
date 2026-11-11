<?php

declare(strict_types=1);

use DoleLeilao\Auction\Infrastructure\WinnerMessageMail;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Identity\Infrastructure\Notifications\VerifyAuctioneerEmail;
use DoleLeilao\Notification\Infrastructure\AuctionStartingSoonMail;
use DoleLeilao\Notification\Infrastructure\NotificationRecord;
use Illuminate\Support\Facades\Notification;

/*
 * Fase 5: pós-leilão (RF-E06 a RF-E08, RF-G05, RF-G06) e e-mails com a marca da loja.
 */

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('UTC')));
});

/** Lote encerrado com 1 arrematação da Ana (R$ 1.500,00) e 1 item sem venda. */
function awardedAuction(): array
{
    $setup = scheduledAuction();
    $ana = eligibleBidder(1);
    as_($setup['auth']['token'])->postJson("/api/v1/auctioneer/auctions/{$setup['auction']['id']}/start")->assertOk();
    tick();
    bid($ana['token'], $setup['items'][0]['id'], 150000)->assertCreated();
    as_($setup['auth']['token'])->postJson("/api/v1/auctioneer/items/{$setup['items'][0]['id']}/close")->assertOk();
    $award = as_($setup['auth']['token'])->getJson('/api/v1/auctioneer/awards')->json('data.0');

    return $setup + ['ana' => $ana, 'award' => $award];
}

describe('arrematações no painel (RF-E08, RF-G06)', function () {
    it('lista com o arrematante, o valor e o status inicial', function () {
        ['auth' => $auth, 'award' => $award] = awardedAuction();

        expect($award)->toMatchArray(['amount_cents' => 150000, 'status' => 'awaiting_contact', 'status_label' => 'Aguardando contato'])
            ->and($award['item']['title'])->toBe('Rolex Datejust 1985')
            ->and($award['bidder']['name'])->toBe('Pessoa1 Teste')
            ->and($award['bidder']['phone'])->toBe('(21) 99876-5432')
            ->and($award['bidder']['cpf_masked'])->toContain('***');
        as_($auth['token'])->getJson('/api/v1/auctioneer/awards?status=open')->assertJsonCount(1, 'data')->assertJsonPath('counts.awaiting_contact', 1);
    });

    it('muda o status e guarda observações', function () {
        ['auth' => $auth, 'award' => $award] = awardedAuction();

        as_($auth['token'])->patchJson("/api/v1/auctioneer/awards/{$award['id']}", ['status' => 'paid', 'notes' => 'Pix recebido'])
            ->assertOk()->assertJsonPath('status_label', 'Pago')->assertJsonPath('notes', 'Pix recebido');
        as_($auth['token'])->patchJson("/api/v1/auctioneer/awards/{$award['id']}", ['status' => 'perdido'])->assertUnprocessable();
    });

    it('exporta CSV para planilha, com CPF completo e valor em reais', function () {
        ['auth' => $auth, 'auction' => $auction] = awardedAuction();

        $csv = as_($auth['token'])->get("/api/v1/auctioneer/awards/export?auction={$auction['id']}", ['Accept' => 'application/json'])->assertOk()->streamedContent();
        $lines = explode("\n", trim($csv));

        expect($csv)->toStartWith("\xEF\xBB\xBF")
            ->and($lines[0])->toContain('Leilão;Data;Item;Posição;Arrematante')
            ->and($lines[1])->toContain('Rolex Datejust 1985')
            ->and($lines[1])->toContain('1.500,00')
            ->and($lines[1])->toContain('"Aguardando contato"');
    });

    it('arrematação de uma loja não aparece em outra', function () {
        awardedAuction();
        $other = liveStore(['email' => 'outro@x.com', 'slug' => 'outra-loja', 'document' => '04.252.011/0001-10', 'company_name' => 'Outra Loja']);

        as_($other['token'])->getJson('/api/v1/auctioneer/awards')->assertJsonCount(0, 'data');
    });
});

describe('mensagem ao vencedor (RF-G05, D-25)', function () {
    it('vem pronta a partir do modelo da loja, que pode ser editado e voltar ao padrão', function () {
        ['auth' => $auth, 'award' => $award] = awardedAuction();

        $draft = as_($auth['token'])->getJson("/api/v1/auctioneer/awards/{$award['id']}/message")->json('body');
        expect($draft)->toStartWith('Olá, Pessoa1 Teste! Parabéns: você arrematou Rolex Datejust 1985 no leilão Relógios de Coleção por R$ 1.500,00.')
            ->and($draft)->toContain('WhatsApp (11) 98765-4321');

        as_($auth['token'])->putJson('/api/v1/auctioneer/winner-message-template', ['template' => 'Oi {apelido}! {item} é seu por {valor}.'])
            ->assertOk()->assertJsonPath('is_default', false)->assertJsonCount(8, 'fields');
        as_($auth['token'])->getJson("/api/v1/auctioneer/awards/{$award['id']}/message")->assertJsonPath('body', 'Oi Pessoa1 T.! Rolex Datejust 1985 é seu por R$ 1.500,00.');

        as_($auth['token'])->putJson('/api/v1/auctioneer/winner-message-template', ['template' => null])->assertJsonPath('is_default', true);
    });

    it('envia por e-mail, aparece no sino e na conta, e devolve o link do WhatsApp', function () {
        Notification::fake();
        ['auth' => $auth, 'award' => $award, 'ana' => $ana] = awardedAuction();

        $response = as_($auth['token'])->postJson("/api/v1/auctioneer/awards/{$award['id']}/message", [
            'body' => "Olá! Pagamento por Pix.\nAté amanhã.",
            'channels' => ['email', 'whatsapp'],
        ])->assertCreated();

        expect($response->json('whatsapp_url'))->toBe('https://wa.me/5521998765432?text='.rawurlencode("Olá! Pagamento por Pix.\nAté amanhã."));
        $bidder = BidderUser::query()->where('public_id', $ana['id'])->firstOrFail();
        Notification::assertSentTo($bidder, WinnerMessageMail::class, fn (WinnerMessageMail $m) => $m->body === "Olá! Pagamento por Pix.\nAté amanhã.");
        expect(NotificationRecord::query()->withoutGlobalScope('tenant')->where('kind', 'winner_message')->value('title'))->toBe('Mensagem de Leilões Silva');

        as_($ana['token'])->getJson('/api/v1/bidders/me/awards')
            ->assertOk()
            ->assertJsonPath('data.0.item.title', 'Rolex Datejust 1985')
            ->assertJsonPath('data.0.status_label', 'Aguardando contato')
            ->assertJsonPath('data.0.messages.0.body', "Olá! Pagamento por Pix.\nAté amanhã.");
    });

    it('só pelo WhatsApp não manda e-mail, mas fica registrado na conta', function () {
        Notification::fake();
        ['auth' => $auth, 'award' => $award, 'ana' => $ana] = awardedAuction();

        as_($auth['token'])->postJson("/api/v1/auctioneer/awards/{$award['id']}/message", ['body' => 'Oi!', 'channels' => ['whatsapp']])->assertCreated();
        Notification::assertNotSentTo(BidderUser::query()->where('public_id', $ana['id'])->firstOrFail(), WinnerMessageMail::class);
        as_($ana['token'])->getJson('/api/v1/bidders/me/awards')->assertJsonPath('data.0.messages.0.channels', ['whatsapp']);

        as_($auth['token'])->postJson("/api/v1/auctioneer/awards/{$award['id']}/message", ['body' => 'Oi!', 'channels' => ['sms']])
            ->assertUnprocessable()->assertJsonPath('errors.channels.0', 'Escolha como enviar: e-mail ou WhatsApp.');
    });
});

describe('alertas por e-mail (RF-E06)', function () {
    it('avisa quem marcou "Quero participar" e quem favoritou um item, no prazo de cada um, uma vez só', function () {
        Notification::fake();
        ['products' => $products] = scheduledAuction(); // começa 2026-11-21 23:00 UTC
        $ana = eligibleBidder(1);
        $bruno = eligibleBidder(2);
        $carla = eligibleBidder(3);
        $semAlerta = eligibleBidder(4);
        as_($ana['token'])->putJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertCreated();
        as_($bruno['token'])->putJson("/api/v1/bidders/me/favorites/{$products[0]['id']}")->assertSuccessful();
        as_($bruno['token'])->patchJson('/api/v1/bidders/me', ['alert_minutes' => 60])->assertOk()->assertJsonPath('alert_minutes', 60);
        as_($semAlerta['token'])->putJson('/api/v1/bidders/me/participations/relogios-de-colecao')->assertCreated();
        as_($semAlerta['token'])->patchJson('/api/v1/bidders/me', ['alert_minutes' => null])->assertOk();
        as_($carla['token'])->patchJson('/api/v1/bidders/me', ['alert_minutes' => 45])->assertUnprocessable();

        $user = fn (array $b) => BidderUser::query()->where('public_id', $b['id'])->firstOrFail();

        $this->travelTo(new DateTimeImmutable('2026-11-21 21:59:00', new DateTimeZone('UTC')));
        $this->artisan('auction:send-alerts')->assertSuccessful();
        Notification::assertSentTimes(AuctionStartingSoonMail::class, 0);

        $this->travelTo(new DateTimeImmutable('2026-11-21 22:00:00', new DateTimeZone('UTC'))); // 60 min antes
        $this->artisan('auction:send-alerts')->assertSuccessful();
        Notification::assertSentTo($user($bruno), AuctionStartingSoonMail::class, fn ($m) => $m->minutes === 60 && str_ends_with($m->url, '/leiloes-silva/leilao/relogios-de-colecao'));
        Notification::assertNotSentTo($user($ana), AuctionStartingSoonMail::class);

        $this->travelTo(new DateTimeImmutable('2026-11-21 22:30:00', new DateTimeZone('UTC'))); // 30 min antes
        $this->artisan('auction:send-alerts')->assertSuccessful();
        $this->artisan('auction:send-alerts')->assertSuccessful();
        Notification::assertSentToTimes($user($ana), AuctionStartingSoonMail::class, 1);
        Notification::assertSentToTimes($user($bruno), AuctionStartingSoonMail::class, 1);
        Notification::assertNotSentTo($user($semAlerta), AuctionStartingSoonMail::class);
        Notification::assertNotSentTo($user($carla), AuctionStartingSoonMail::class);
    });

    it('lista os próximos leilões da pessoa (participando ou com favoritos)', function () {
        ['products' => $products] = scheduledAuction();
        $ana = eligibleBidder(1);
        as_($ana['token'])->getJson('/api/v1/bidders/me/upcoming-auctions')->assertJsonCount(0, 'data');
        as_($ana['token'])->putJson("/api/v1/bidders/me/favorites/{$products[1]['id']}")->assertSuccessful();

        as_($ana['token'])->getJson('/api/v1/bidders/me/upcoming-auctions')
            ->assertJsonPath('data.0.auction.name', 'Relógios de Coleção')
            ->assertJsonPath('data.0.participating', false)
            ->assertJsonPath('data.0.has_favorites', true);
    });
});

describe('e-mails com a marca (specs/07, D-01, D-06, D-20)', function () {
    it('e-mail da loja leva a cor primária dela e o rodapé com UTM', function () {
        ['ana' => $ana] = awardedAuction();
        $bidder = BidderUser::query()->where('public_id', $ana['id'])->firstOrFail();

        $html = (string) (new WinnerMessageMail('leiloes-silva', 'Leilões Silva', 'Rolex', "Linha 1\nLinha 2"))->toMail($bidder)->render();

        expect($html)->toContain('Leilões Silva')
            ->toContain('background:#8A5A38') // primária padrão do modelo Relógios (cobre)
            ->toContain('Linha 1<br>')
            ->toContain('desenvolvido por Doleleilao.com.br')
            ->toContain('utm_source=leiloes-silva&amp;utm_medium=email');
    });

    it('e-mail para o leiloeiro leva a marca Dole Leilão', function () {
        $auth = registerAuctioneer();
        $html = (string) (new VerifyAuctioneerEmail)->toMail($auth['user'])->render();

        expect($html)->toContain('DOLE')->toContain('Confirmar e-mail')->not->toContain('desenvolvido por');
    });
});

describe('histórico do produto e volta para leilão', function () {
    it('mostra o lote, quem arrematou e os lances do item', function () {
        ['auth' => $auth, 'products' => $products] = awardedAuction();

        $history = as_($auth['token'])->getJson("/api/v1/auctioneer/products/{$products[0]['id']}/history")->assertOk()->json('data');
        expect($history)->toHaveCount(1)
            ->and($history[0]['auction']['name'])->toBe('Relógios de Coleção')
            ->and($history[0]['item']['status'])->toBe('closed_sold')
            ->and($history[0]['award']['bidder']['name'])->toBe('Pessoa1 Teste')
            ->and($history[0]['award']['amount_cents'])->toBe(150000)
            ->and($history[0]['bids'][0])->toMatchArray(['amount_cents' => 150000, 'type' => 'manual', 'voided' => false]);
    });

    it('retornar item arrematado: produto volta disponível e a arrematação vira desistência com o motivo', function () {
        ['auth' => $auth, 'products' => $products, 'award' => $award] = awardedAuction();

        as_($auth['token'])->postJson("/api/v1/auctioneer/products/{$products[0]['id']}/return-to-catalog", ['reason' => 'Comprador desistiu'])
            ->assertOk()->assertJsonPath('status', 'available');

        $updated = as_($auth['token'])->getJson('/api/v1/auctioneer/awards')->json('data.0');
        expect($updated['id'])->toBe($award['id'])
            ->and($updated['status'])->toBe('withdrawn')
            ->and($updated['notes'])->toBe('Item devolvido para leilão em 20/11/2026: Comprador desistiu');

        as_($auth['token'])->postJson("/api/v1/auctioneer/products/{$products[0]['id']}/return-to-catalog")->assertConflict();
        // Pode entrar num lote novo.
        as_($auth['token'])->getJson('/api/v1/auctioneer/auctions/selectable-products')->assertJsonFragment(['id' => $products[0]['id']]);
    });

    it('produto em lote não volta pelo atalho', function () {
        ['auth' => $auth, 'products' => $products] = scheduledAuction();

        as_($auth['token'])->postJson("/api/v1/auctioneer/products/{$products[0]['id']}/return-to-catalog")
            ->assertConflict()->assertJsonPath('detail', 'O produto está num lote. Tire-o do lote ou espere o leilão terminar.');
    });
});
