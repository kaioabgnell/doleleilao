<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Banners (RF-B06) e categorias (RF-B07) da loja. Tabelas do tenant: sempre com auctioneer_id. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('slug', 60);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('image_path')->nullable();
            $table->timestamps(6);

            $table->unique(['auctioneer_id', 'slug']);
            $table->index(['auctioneer_id', 'position']);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('image_mobile');
            $table->string('image_desktop')->nullable();
            $table->string('link_url', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->dateTime('starts_at', 6)->nullable();
            $table->dateTime('ends_at', 6)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps(6);

            $table->index(['auctioneer_id', 'active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::dropIfExists('categories');
    }
};
