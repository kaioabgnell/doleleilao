<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DoleLeilao\Notification\Infrastructure\AuctionStartingAlerts;
use Illuminate\Console\Command;

/** Alertas por e-mail "o leilão vai começar" (RF-E06). Roda a cada minuto pelo agendador. */
final class SendAuctionAlertsCommand extends Command
{
    protected $signature = 'auction:send-alerts';

    protected $description = 'Envia por e-mail o aviso de que o leilão vai começar, no prazo escolhido por cada participante';

    public function handle(AuctionStartingAlerts $alerts): int
    {
        $sent = $alerts->send();
        if ($sent > 0) {
            $this->info("{$sent} alerta(s) enviado(s).");
        }

        return self::SUCCESS;
    }
}
