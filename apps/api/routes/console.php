<?php

use DoleLeilao\Notification\Infrastructure\NotificationRecord;
use Illuminate\Support\Facades\Schedule;

// Apaga notificações com mais de 90 dias (RF-N07) e outros registros com prazo de guarda.
Schedule::command('model:prune', ['--model' => [NotificationRecord::class]])->dailyAt('03:30')->timezone('America/Sao_Paulo');

// Tokens vencidos do Sanctum.
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:40')->timezone('America/Sao_Paulo');

// Avisos de véspera e do dia dos leilões (RF-N05).
Schedule::command('auction:send-reminders')->dailyAt('08:00')->timezone('America/Sao_Paulo')->withoutOverlapping();

// Alertas por e-mail "o leilão vai começar" (RF-E06).
Schedule::command('auction:send-alerts')->everyMinute()->withoutOverlapping();
