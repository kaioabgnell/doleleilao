<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DoleLeilao\Auction\Application\Actor;
use DoleLeilao\Auction\Application\ControlAuction;
use DoleLeilao\Auction\Application\RunAuctionClock;
use DoleLeilao\Auction\Infrastructure\RealtimeHealth;
use DoleLeilao\Shared\Infrastructure\Scalar;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Relógio dos leilões (regra 8, T-03): laço de 250 ms supervisionado (Supervisor em produção,
 * `npm run dev` no desenvolvimento). Também vigia o WebSocket: com o Reverb fora do ar por duas
 * verificações seguidas, pausa os itens em pregão (regra 9). Quem retoma é o leiloeiro.
 */
final class AuctionClockCommand extends Command
{
    protected $signature = 'auction:clock {--once : uma volta só (testes)} {--interval=250 : milissegundos entre as voltas}';

    protected $description = 'Inicia lotes, encerra itens, abre o próximo item e pausa o pregão se o WebSocket cair';

    private bool $running = true;

    public function handle(RunAuctionClock $clock, ControlAuction $control, CurrentTenant $tenant): int
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->running = false);
            pcntl_signal(SIGINT, fn () => $this->running = false);
        }

        $interval = max(50, (int) $this->option('interval')) * 1000;
        $watchRealtime = config('broadcasting.default') === 'reverb';
        $lastHealthCheck = 0.0;
        $failures = 0;
        if (! $this->option('once')) {
            $this->info('Relógio dos leilões rodando a cada '.($interval / 1000).' ms.');
        }

        while ($this->running) {
            try {
                $changes = $clock->tick();
                if ($changes > 0 && $this->output->isVerbose()) {
                    $this->line(now()->format('H:i:s.v')." {$changes} evento(s)");
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error($e->getMessage());
            }

            if ($watchRealtime && microtime(true) - $lastHealthCheck >= 2) {
                $lastHealthCheck = microtime(true);
                try {
                    $healthy = RealtimeHealth::reachable() && RealtimeHealth::consumeFailures() === 0;
                    $failures = $healthy ? 0 : $failures + 1;
                    if ($failures === 2) {
                        $this->pauseRunningItems($control, $tenant);
                    }
                } catch (\Throwable $e) {
                    report($e); // a vigilância do WebSocket nunca derruba o relógio
                }
            }

            if ($this->option('once')) {
                break;
            }
            usleep($interval);
        }

        return self::SUCCESS;
    }

    private function pauseRunningItems(ControlAuction $control, CurrentTenant $tenant): void
    {
        $auctions = DB::table('auctions')
            ->join('auction_items', 'auction_items.auction_id', '=', 'auctions.id')
            ->where('auctions.status', 'live')
            ->where('auction_items.status', 'open')
            ->get(['auctions.public_id', 'auctions.auctioneer_id']);

        foreach ($auctions as $auction) {
            try {
                $publicId = Scalar::string($auction->public_id);
                $tenant->runAs(Scalar::int($auction->auctioneer_id), fn () => $control->pause($publicId, Actor::system(), 'realtime_offline'));
                $this->warn("WebSocket fora do ar: lote {$publicId} pausado.");
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
