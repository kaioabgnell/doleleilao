<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lote encerrado pode sair do histórico da loja sem ser apagado. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('visible_in_store')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', fn (Blueprint $t) => $t->dropColumn('visible_in_store'));
    }
};
