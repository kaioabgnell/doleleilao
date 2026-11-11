<?php

declare(strict_types=1);

use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionItemRecord;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * Roadmap da Fase 3: "N processos disputando o mesmo item, com exatamente 1 vencedor e nenhum lance
 * perdido". Processos PHP de verdade, cada um com a própria conexão ao MySQL, contra a trava
 * pessimista (FOR UPDATE) do PlaceBid. Os dados precisam ser gravados (sem a transação dos testes).
 */

uses(TestCase::class, DatabaseTruncation::class);

afterEach(function () {
    // Os outros testes usam transação: deixa o banco limpo para eles.
    $this->artisan('db:wipe');
    $this->artisan('migrate');
});

it('com 8 processos disputando o mesmo item, nenhum lance se perde e há 1 só líder', function () {
    $auth = liveStore();
    withPaymentCard($auth);
    $product = createProduct($auth, ['starting_price_cents' => 100000, 'increment_cents' => 1000]);
    app('auth')->forgetGuards();
    $auction = test()->withToken($auth['token'])->postJson('/api/v1/auctioneer/auctions', [
        'name' => 'Disputa', 'starts_at' => now()->addDay()->toIso8601String(),
        'settings' => ['item_duration_s' => 900],
        'items' => [['product_id' => $product['id']]],
    ])->assertCreated()->json();
    test()->withToken($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/schedule")->assertOk();
    test()->withToken($auth['token'])->postJson("/api/v1/auctioneer/auctions/{$auction['id']}/start")->assertOk();
    $this->artisan('auction:clock', ['--once' => true])->assertSuccessful();
    $item = $auction['items'][0]['id'];

    $bidders = [];
    foreach (range(1, 8) as $n) {
        app('auth')->forgetGuards();
        $b = registerBidder('leiloes-silva', ['email' => "c{$n}@exemplo.com", 'cpf' => validCpf(700 + $n)]);
        $user = BidderUser::query()->where('public_id', $b['bidder']['id'])->firstOrFail();
        $user->markEmailVerified();
        $bidders[] = $user->id;
    }

    $env = ['APP_ENV' => 'testing', 'DB_DATABASE' => 'doleleilao_test', 'BROADCAST_CONNECTION' => 'null', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'];
    $start = function (int $bidder, ?string $key = null) use ($item, $auth, $env): array {
        $proc = proc_open(
            array_values(array_filter([PHP_BINARY, __DIR__.'/bid-worker.php', $item, (string) $bidder, (string) $auth['user']->auctioneer_id, $key === null ? '15' : '3', $key])),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $env + getenv(),
        ) ?: throw new RuntimeException('proc_open falhou');

        return [$proc, $pipes];
    };

    $processes = [];
    foreach ($bidders as $bidder) {
        $processes[] = $start($bidder);
    }
    // Clique duplo em dois processos ao mesmo tempo: mesma chave, um único lance.
    foreach ([1, 2] as $_) {
        $processes[] = $start($bidders[0], 'clique-duplo-0001');
    }

    $accepted = [];
    foreach ($processes as [$proc, $p]) {
        $out = stream_get_contents($p[1]);
        $err = stream_get_contents($p[2]);
        expect(proc_close($proc))->toBe(0, $err);
        $result = json_decode((string) $out, true, flags: JSON_THROW_ON_ERROR);
        array_push($accepted, ...$result['accepted']);
    }

    $bids = DB::table('bids')->orderBy('id')->get();
    $record = AuctionItemRecord::query()->withoutGlobalScope('tenant')->where('public_id', $item)->firstOrFail();

    // Nenhum lance aceito se perdeu, e nada além deles foi gravado.
    expect($bids->pluck('public_id')->sort()->values()->all())->toBe(collect($accepted)->unique()->sort()->values()->all())
        ->and(count($accepted))->toBeGreaterThan(10);

    // Cada lance gravado respeita o incremento sobre o anterior (ordem = ordem de gravação).
    $previous = null;
    foreach ($bids as $bid) {
        if ($previous !== null) {
            expect((int) $bid->amount)->toBeGreaterThanOrEqual((int) $previous->amount + 1000)
                ->and((int) $bid->bidder_id)->not->toBe((int) $previous->bidder_id);
        }
        $previous = $bid;
    }

    // O item aponta exatamente para o último lance: 1 líder, contagem certa.
    expect($record->current_price)->toBe((int) $bids->last()->amount)
        ->and($record->leading_bidder_id)->toBe((int) $bids->last()->bidder_id)
        ->and($record->bids_count)->toBe($bids->count())
        ->and(DB::table('bids')->where('idempotency_key', 'clique-duplo-0001')->count())->toBeLessThanOrEqual(1);

    // Encerrado, vira exatamente 1 arrematação.
    test()->withToken($auth['token'])->postJson("/api/v1/auctioneer/items/{$item}/close")->assertOk();
    expect(DB::table('awards')->count())->toBe(1)
        ->and((int) DB::table('awards')->value('bidder_id'))->toBe($record->leading_bidder_id);
})->group('concurrency');
