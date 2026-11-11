<?php

declare(strict_types=1);

/*
 * Processo de lances para o teste de concorrência: sobe a aplicação de verdade (sem transação de teste)
 * e tenta dar lances no mesmo item que os outros processos, sempre no "próximo mínimo" que acabou de
 * ler — o pior caso para empates. Imprime em JSON os lances aceitos e recusados.
 *
 * Uso: php bid-worker.php {item} {bidderId} {auctioneerId} {tentativas} {chave-repetida?}
 */

use DoleLeilao\Auction\Application\PlaceBid;
use DoleLeilao\Auction\Domain\BidRejected;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $item, $bidderId, $auctioneerId, $attempts] = $argv;
$sharedKey = $argv[5] ?? null;
app(CurrentTenant::class)->set((int) $auctioneerId);
$placeBid = app(PlaceBid::class);

$accepted = [];
$rejected = 0;
for ($i = 0; $i < (int) $attempts; $i++) {
    $row = DB::table('auction_items')->where('public_id', $item)->first(['current_price', 'starting_price', 'increment']);
    $amount = $row->current_price === null ? (int) $row->starting_price : (int) $row->current_price + (int) $row->increment;
    $key = $sharedKey ?? "w{$bidderId}-{$i}-".bin2hex(random_bytes(4));
    try {
        $outcome = $placeBid->execute((int) $bidderId, $item, $amount, $key);
        if (! $outcome->replayed) {
            $accepted[] = $outcome->bidPublicId;
        }
    } catch (BidRejected) {
        $rejected++;
    }
    usleep(random_int(0, 3000));
}

echo json_encode(['accepted' => $accepted, 'rejected' => $rejected]);
