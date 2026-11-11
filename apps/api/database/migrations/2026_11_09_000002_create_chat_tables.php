<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fase 4: chat do auditório (specs/05-modelo-de-dados.md#chat). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            // Autor: um participante ou uma conta do painel (o leiloeiro).
            $table->foreignId('bidder_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('auctioneer_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('body', 2000);
            $table->foreignId('reply_to_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->dateTime('created_at', 6);

            $table->index(['auction_id', 'id']);
        });

        Schema::create('chat_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->dateTime('until', 6)->nullable(); // nulo = até o fim do leilão
            $table->unsignedBigInteger('muted_by')->nullable();
            $table->timestamps(6);

            $table->unique(['auction_id', 'bidder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mutes');
        Schema::dropIfExists('chat_messages');
    }
};
