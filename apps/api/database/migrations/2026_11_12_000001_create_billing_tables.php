<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobrança (D-47, D-54): faixas da taxa, cartão do leiloeiro e o débito da taxa por lote encerrado.
 * Valores em centavos; datas em UTC. Os débitos sobrevivem à exclusão do lote (guardam o nome e o
 * título dos itens como cópia).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Faixas da taxa, sem tela de gestão por enquanto: mudar a taxa = fechar as faixas atuais
        // (valid_until) e inserir as novas (valid_from). Tabela da plataforma, sem auctioneer_id.
        Schema::create('fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('min_amount'); // inclusive
            $table->unsignedBigInteger('max_amount')->nullable(); // inclusive; nulo = sem teto
            $table->unsignedSmallInteger('rate_bps'); // pontos-base: 750 = 7,5%
            $table->dateTime('valid_from', 6);
            $table->dateTime('valid_until', 6)->nullable();
            $table->timestamps(6);

            $table->index(['valid_from', 'min_amount']);
        });

        // D-47: cobrança a partir de 01/12/2026, 00:00 em Brasília.
        $from = '2026-12-01 03:00:00';
        $now = now('UTC');
        DB::table('fee_tiers')->insert([
            ['min_amount' => 0, 'max_amount' => 10000, 'rate_bps' => 750, 'valid_from' => $from, 'created_at' => $now, 'updated_at' => $now],
            ['min_amount' => 10001, 'max_amount' => 50000, 'rate_bps' => 600, 'valid_from' => $from, 'created_at' => $now, 'updated_at' => $now],
            ['min_amount' => 50001, 'max_amount' => 100000, 'rate_bps' => 500, 'valid_from' => $from, 'created_at' => $now, 'updated_at' => $now],
            ['min_amount' => 100001, 'max_amount' => null, 'rate_bps' => 250, 'valid_from' => $from, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Sem gateway: nunca o número completo nem o CVV (PCI DSS).
        Schema::create('payment_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auctioneer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand', 16);
            $table->char('last4', 4);
            $table->string('holder_name', 60);
            $table->unsignedTinyInteger('exp_month');
            $table->unsignedSmallInteger('exp_year');
            $table->timestamps(6);
        });

        Schema::create('fee_charges', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('auction_name', 80);
            $table->string('fee_payer', 12);
            $table->unsignedBigInteger('amount');
            $table->string('status', 12)->default('pending');
            // Cartão no momento do débito (pode ser trocado depois).
            $table->string('card_brand', 16)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->dateTime('charged_at', 6)->nullable();
            $table->timestamps(6);

            $table->index(['auctioneer_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // Taxa de cada item arrematado, com a faixa aplicada (cópia).
        Schema::create('award_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_charge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('item_title', 120);
            $table->unsignedBigInteger('base_amount');
            $table->unsignedSmallInteger('rate_bps');
            $table->unsignedBigInteger('fee_amount');
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        foreach (['award_fees', 'fee_charges', 'payment_cards', 'fee_tiers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
