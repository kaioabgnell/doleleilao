<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DoleLeilao\Notification\Infrastructure\AuctionNotifications;
use Illuminate\Console\Command;

/** Avisos de véspera e do dia (RF-N05), às 8h de Brasília. Rodar de novo não duplica avisos. */
final class SendAuctionRemindersCommand extends Command
{
    protected $signature = 'auction:send-reminders';

    protected $description = 'Avisa "o leilão é amanhã" e "o leilão é hoje" a quem marcou Quero participar e ao leiloeiro';

    public function handle(AuctionNotifications $notifications): int
    {
        $this->info($notifications->sendDailyReminders().' aviso(s) enviado(s).');

        return self::SUCCESS;
    }
}
