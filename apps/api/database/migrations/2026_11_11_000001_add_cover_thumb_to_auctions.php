<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Capa própria do lote: `cover_path` (até 1600 px) e a miniatura para listas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('cover_thumb_path')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', fn (Blueprint $t) => $t->dropColumn('cover_thumb_path'));
    }
};
