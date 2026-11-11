<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notificações do sino (D-43) e data em que a loja ficou pública pela primeira vez. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('recipient_type', 16);
            $table->unsignedBigInteger('recipient_id');
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('title', 160);
            $table->string('body', 500);
            $table->string('url', 255);
            $table->string('dedupe_key', 191)->unique();
            $table->dateTime('read_at', 6)->nullable();
            $table->dateTime('created_at', 6)->index();

            $table->index(['recipient_type', 'recipient_id', 'id']);
        });

        Schema::table('storefront_settings', function (Blueprint $table) {
            $table->dateTime('published_at', 6)->nullable()->after('socials');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', fn (Blueprint $table) => $table->dropColumn('published_at'));
        Schema::dropIfExists('notifications');
    }
};
