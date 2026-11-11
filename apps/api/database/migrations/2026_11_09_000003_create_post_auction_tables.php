<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fase 5: mensagem ao vencedor, alertas por e-mail e o modelo da mensagem da loja. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('winner_messages', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auctioneer_user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->json('channels');
            $table->dateTime('sent_at', 6);

            $table->index(['award_id', 'id']);
        });

        // Registro de alertas enviados: um por pessoa por leilão (RF-E06).
        Schema::create('auction_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('minutes_before');
            $table->dateTime('sent_at', 6);

            $table->unique(['auction_id', 'bidder_id']);
        });

        Schema::table('bidders', function (Blueprint $table) {
            // Minutos antes do início para o alerta por e-mail; nulo = não quer alertas.
            $table->unsignedSmallInteger('alert_minutes')->nullable()->default(30)->after('avatar_key');
        });

        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->text('winner_message_template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', fn (Blueprint $t) => $t->dropColumn('winner_message_template'));
        Schema::table('bidders', fn (Blueprint $t) => $t->dropColumn('alert_minutes'));
        Schema::dropIfExists('auction_alerts');
        Schema::dropIfExists('winner_messages');
    }
};
