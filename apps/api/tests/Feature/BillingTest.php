<?php

declare(strict_types=1);

use DoleLeilao\Billing\Application\ChargeAuctionFees;
use DoleLeilao\Billing\Infrastructure\Eloquent\AwardFeeRecord;
use DoleLeilao\Billing\Infrastructure\Eloquent\FeeChargeRecord;
use DoleLeilao\Billing\Infrastructure\Eloquent\PaymentCardRecord;
use Illuminate\Support\Facades\DB;

/*
 * Cobrança (D-47, D-54): cartão do leiloeiro em Configurações → Pagamento, cartão exigido para
 * agendar, "quem paga a taxa" no lote e o débito da taxa quando o lote termina.
 */

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('UTC')));
});

/**
 * Lote com um item por valor, todos arrematados pela mesma pessoa pelo valor inicial.
 *
 * @param  list<int>  $prices  em centavos
 * @return array{auth: array<string, mixed>, auction: array<string, mixed>}
 */
function soldAuction(array $prices, string $startsAt, array $settings = []): array
{
    $auth = liveStore();
    withPaymentCard($auth);
    $products = [];
    foreach ($prices as $i => $cents) {
        $products[] = createProduct($auth, ['title' => 'Produto '.chr(65 + $i), 'starting_price_cents' => $cents, 'increment_cents' => 100]);
    }
    $auction = draftAuction($auth, $products, $startsAt, ['settings' => ['gap_s' => 10, ...$settings]]);
    as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/schedule")->assertOk();
    $ana = eligibleBidder(1);

    test()->travelTo((new DateTimeImmutable($startsAt))->setTimezone(new DateTimeZone('UTC')));
    tick(); // o relógio inicia o lote na hora marcada (T-16)
    foreach ($auction['items'] as $i => $item) {
        tick();
        bid($ana['token'], $item['id'], $prices[$i])->assertCreated();
        as_($auth['token'])->postJson("/api/v1/auctioneer/items/{$item['id']}/close")->assertOk();
        test()->travel(11)->seconds();
    }
    tick();
    as_($auth['token'])->getJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertJsonPath('status', 'finished');

    return ['auth' => $auth, 'auction' => $auction];
}

describe('Configurações → Pagamento', function () {
    it('mostra as faixas da taxa e que ainda não há cartão', function () {
        $auth = liveStore();

        as_($auth['token'])->getJson('/api/v1/auctioneer/billing')
            ->assertOk()
            ->assertJsonPath('card', null)
            ->assertJsonPath('fees_start_at', '2026-12-01T03:00:00.000Z')
            ->assertJsonPath('fees_started', false)
            ->assertJsonPath('fee_tiers', [
                ['min_cents' => 0, 'max_cents' => 10000, 'rate_bps' => 750, 'valid_from' => '2026-12-01T03:00:00.000Z'],
                ['min_cents' => 10001, 'max_cents' => 50000, 'rate_bps' => 600, 'valid_from' => '2026-12-01T03:00:00.000Z'],
                ['min_cents' => 50001, 'max_cents' => 100000, 'rate_bps' => 500, 'valid_from' => '2026-12-01T03:00:00.000Z'],
                ['min_cents' => 100001, 'max_cents' => null, 'rate_bps' => 250, 'valid_from' => '2026-12-01T03:00:00.000Z'],
            ]);
    });

    it('salva o cartão sem guardar o número completo nem o código de segurança', function () {
        $auth = liveStore();

        as_($auth['token'])->putJson('/api/v1/auctioneer/billing/card', [
            'number' => '5555 5555 5555 4444', 'holder_name' => 'maria silva', 'expiry' => '07/31', 'cvv' => '987',
        ])->assertOk()->assertJsonPath('card', [
            'brand' => 'mastercard', 'brand_label' => 'Mastercard', 'last4' => '4444',
            'holder_name' => 'MARIA SILVA', 'exp_month' => 7, 'exp_year' => 2031,
        ]);

        $row = json_encode(DB::table('payment_cards')->first());
        expect($row)->not->toContain('5555555555554444')->not->toContain('555555')->not->toContain('987');
    });

    it('trocar o cartão substitui o anterior', function () {
        $auth = liveStore();
        withPaymentCard($auth);
        withPaymentCard($auth, '5555 5555 5555 4444');

        expect(PaymentCardRecord::withoutGlobalScope('tenant')->count())->toBe(1);
        as_($auth['token'])->getJson('/api/v1/auctioneer/billing')->assertJsonPath('card.last4', '4444');
    });

    it('aponta os campos com erro', function () {
        $auth = liveStore();

        as_($auth['token'])->putJson('/api/v1/auctioneer/billing/card', [
            'number' => '4111 1111 1111 1112', 'holder_name' => 'Maria', 'expiry' => '10/26', 'cvv' => '12',
        ])->assertUnprocessable()->assertJsonPath('errors', [
            'number' => ['Confira o número do cartão.'],
            'expiry' => ['Este cartão está vencido.'],
            'cvv' => ['O código de segurança tem 3 dígitos.'],
        ]);
        as_($auth['token'])->putJson('/api/v1/auctioneer/billing/card', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['number', 'holder_name', 'expiry', 'cvv']);
        expect(PaymentCardRecord::withoutGlobalScope('tenant')->count())->toBe(0);
    });

    it('cada loja vê só o próprio cartão', function () {
        $auth = liveStore();
        withPaymentCard($auth);
        $other = liveStore(['email' => 'outro@x.com', 'slug' => 'outra-loja', 'document' => '04.252.011/0001-10', 'company_name' => 'Outra Loja']);

        as_($other['token'])->getJson('/api/v1/auctioneer/billing')->assertJsonPath('card', null);
    });
});

describe('lote e taxa', function () {
    it('agendar exige o cartão de crédito cadastrado', function () {
        $auth = liveStore();
        $auction = draftAuction($auth, [createProduct($auth)]);

        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/schedule")
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment_card.0', 'Cadastre um cartão de crédito em Configurações → Pagamento para agendar o leilão.');

        withPaymentCard($auth);
        as_($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/schedule")->assertOk()->assertJsonPath('status', 'scheduled');
    });

    it('guarda quem paga a taxa: o leiloeiro assume por padrão ou repassa ao vencedor', function () {
        $auth = liveStore();
        $p = createProduct($auth);
        $auction = draftAuction($auth, [$p]);
        expect($auction['settings']['fee_payer'])->toBe('auctioneer');

        as_($auth['token'])->patchJson("/api/v1/auctioneer/auctions/{$auction['id']}", ['settings' => ['fee_payer' => 'bidder']])
            ->assertOk()->assertJsonPath('settings.fee_payer', 'bidder')->assertJsonPath('settings.chat_enabled', true);
        as_($auth['token'])->patchJson("/api/v1/auctioneer/auctions/{$auction['id']}", ['settings' => ['fee_payer' => 'ninguem']])
            ->assertUnprocessable()->assertJsonPath('errors', ['settings.fee_payer' => ['Escolha quem paga a taxa do leilão.']]);
    });
});

describe('débito da taxa quando o lote termina (D-47, D-54)', function () {
    it('gera um débito no cartão com a taxa de cada item pela faixa do valor', function () {
        $this->travelTo(new DateTimeImmutable('2026-12-04 12:00:00', new DateTimeZone('UTC')));
        ['auction' => $auction] = soldAuction([10000, 20000, 80000], '2026-12-05T20:00:00-03:00', ['fee_payer' => 'bidder']);

        $charge = FeeChargeRecord::withoutGlobalScope('tenant')->with(['fees' => fn ($q) => $q->withoutGlobalScope('tenant')])->sole();
        expect($charge->amount)->toBe(5950) // R$ 7,50 + R$ 12,00 + R$ 40,00
            ->and($charge->status->value)->toBe('pending')
            ->and($charge->fee_payer->value)->toBe('bidder')
            ->and($charge->card_brand?->value)->toBe('visa')
            ->and($charge->card_last4)->toBe('1111')
            ->and($charge->auction_name)->toBe('Relógios de Coleção')
            ->and($charge->fees->map(fn (AwardFeeRecord $f) => [$f->item_title, $f->base_amount, $f->rate_bps, $f->fee_amount])->all())->toBe([
                ['Produto A', 10000, 750, 750],
                ['Produto B', 20000, 600, 1200],
                ['Produto C', 80000, 500, 4000],
            ]);
    });

    it('não cobra nada no período gratuito (até 30/11/2026)', function () {
        soldAuction([10000, 80000], '2026-11-21T20:00:00-03:00');

        expect(FeeChargeRecord::withoutGlobalScope('tenant')->count())->toBe(0);
    });

    it('não duplica o débito se o encerramento for processado de novo', function () {
        $this->travelTo(new DateTimeImmutable('2026-12-04 12:00:00', new DateTimeZone('UTC')));
        soldAuction([10000], '2026-12-05T20:00:00-03:00');
        $auctionId = (int) DB::table('auctions')->value('id');

        app(ChargeAuctionFees::class)->handle($auctionId);

        expect(FeeChargeRecord::withoutGlobalScope('tenant')->count())->toBe(1)
            ->and(AwardFeeRecord::withoutGlobalScope('tenant')->count())->toBe(1);
    });

    it('o débito continua registrado se o lote for apagado depois', function () {
        $this->travelTo(new DateTimeImmutable('2026-12-04 12:00:00', new DateTimeZone('UTC')));
        ['auth' => $auth, 'auction' => $auction] = soldAuction([80000], '2026-12-05T20:00:00-03:00');

        as_($auth['token'])->deleteJson("/api/v1/auctioneer/auctions/{$auction['id']}")->assertNoContent();

        $charge = FeeChargeRecord::withoutGlobalScope('tenant')->sole();
        expect($charge->auction_id)->toBeNull()
            ->and($charge->amount)->toBe(4000)
            ->and(AwardFeeRecord::withoutGlobalScope('tenant')->sole()->award_id)->toBeNull();
    });
});
