<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3: lotes de leilão, itens, lances, automáticos, arrematações, log de auditoria,
 * "Quero participar" e banimentos (specs/05-modelo-de-dados.md). Valores em centavos; datas em UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->dateTime('starts_at', 6);
            $table->string('status', 12)->default('draft');
            $table->json('settings');
            $table->unsignedBigInteger('current_item_id')->nullable();
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('finished_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['auctioneer_id', 'slug']);
            $table->index(['status', 'starts_at']);
            $table->index(['auctioneer_id', 'status', 'starts_at']);
        });

        Schema::create('auction_items', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            // O produto pode ser apagado depois do leilão: o item guarda o título para o histórico.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->unsignedSmallInteger('position');
            $table->unsignedBigInteger('starting_price');
            $table->unsignedBigInteger('increment');
            $table->unsignedBigInteger('current_price')->nullable();
            $table->foreignId('leading_bidder_id')->nullable()->constrained('bidders')->nullOnDelete();
            $table->unsignedInteger('bids_count')->default(0);
            $table->string('status', 16)->default('queued');
            $table->dateTime('opened_at', 6)->nullable();
            $table->dateTime('ends_at', 6)->nullable();
            $table->unsignedInteger('paused_remaining_ms')->nullable();
            $table->dateTime('closed_at', 6)->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps(6);

            $table->unique(['auction_id', 'position']);
            $table->index(['status', 'ends_at']); // relógio (SKIP LOCKED)
            $table->index(['product_id', 'status']);
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('type', 8);
            $table->dateTime('placed_at', 6);
            $table->string('idempotency_key', 64)->nullable();
            $table->dateTime('voided_at', 6)->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->dateTime('created_at', 6)->nullable();

            $table->unique(['bidder_id', 'idempotency_key']);
            $table->index(['auction_item_id', 'voided_at', 'id']);
        });

        Schema::create('auto_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('max_amount');
            $table->boolean('active')->default(true);
            $table->dateTime('registered_at', 6);
            $table->timestamps(6);

            $table->unique(['auction_item_id', 'bidder_id']);
        });

        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->dateTime('awarded_at', 6);
            $table->string('status', 20)->default('awaiting_contact');
            $table->text('notes')->nullable();
            $table->timestamps(6);

            $table->index(['auctioneer_id', 'status']);
            $table->index(['bidder_id', 'awarded_at']);
        });

        // Somente inserção (RF-D05).
        Schema::create('auction_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 40);
            $table->json('payload');
            $table->dateTime('created_at', 6);

            $table->index(['auction_id', 'id']);
        });

        // "Quero participar" (RF-E10, D-44): interesse, não aprovação.
        Schema::create('auction_participations', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->dateTime('enabled_notified_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['auction_id', 'bidder_id']);
            $table->index(['bidder_id', 'enabled_notified_at']);
        });

        Schema::create('bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 300)->nullable();
            $table->unsignedBigInteger('banned_by')->nullable();
            $table->dateTime('banned_at', 6);
            $table->dateTime('lifted_at', 6)->nullable();
            $table->timestamps(6);

            $table->index(['auctioneer_id', 'bidder_id', 'lifted_at']);
        });
    }

    public function down(): void
    {
        foreach (['bans', 'auction_participations', 'auction_audit_log', 'awards', 'auto_bids', 'bids', 'auction_items', 'auctions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
