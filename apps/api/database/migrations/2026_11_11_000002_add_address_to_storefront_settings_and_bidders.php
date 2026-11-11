<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Endereço opcional para envio de produtos: de onde a loja envia (painel → "Dados da empresa")
 * e para onde o participante recebe ("Minha conta" → "Seus dados"). Sem CEP = sem endereço.
 */
return new class extends Migration
{
    private const array COLUMNS = [
        'address_postal_code', 'address_street', 'address_number', 'address_complement',
        'address_district', 'address_city', 'address_state',
    ];

    public function up(): void
    {
        foreach (['storefront_settings', 'bidders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->char('address_postal_code', 8)->nullable();
                $table->string('address_street', 120)->nullable();
                $table->string('address_number', 10)->nullable();
                $table->string('address_complement', 60)->nullable();
                $table->string('address_district', 60)->nullable();
                $table->string('address_city', 60)->nullable();
                $table->char('address_state', 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['storefront_settings', 'bidders'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn(self::COLUMNS));
        }
    }
};
