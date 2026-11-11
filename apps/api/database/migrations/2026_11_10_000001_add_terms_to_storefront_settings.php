<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Termos de uso da loja (regras do leilão), aceitos pelo participante no cadastro. Nulo = texto padrão. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->mediumText('terms')->nullable();
            $table->dateTime('terms_updated_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', fn (Blueprint $t) => $t->dropColumn(['terms', 'terms_updated_at']));
    }
};
