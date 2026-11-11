<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2: catálogo (produtos, fotos, visualizações), participantes por loja, favoritos e
 * "quero leiloar" (specs/05-modelo-de-dados.md). Valores em centavos; datas em UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('reference_price')->nullable();
            $table->unsignedBigInteger('starting_price');
            $table->unsignedBigInteger('increment');
            $table->char('youtube_video_id', 11)->nullable();
            $table->json('attributes');
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps(6);

            $table->unique(['auctioneer_id', 'slug']);
            $table->index(['auctioneer_id', 'status']);
            $table->index(['auctioneer_id', 'category_id']);
        });

        Schema::create('product_photos', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('original_path')->nullable();
            $table->json('variants')->nullable();
            $table->string('status', 12)->default('processing');
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps(6);

            $table->index(['product_id', 'position']);
        });

        Schema::create('product_view_daily', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->primary(['product_id', 'date']);
        });

        Schema::create('bidders', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('nickname', 30);
            $table->text('cpf_enc');
            $table->char('cpf_hash', 64);
            $table->string('email');
            $table->string('phone', 11);
            $table->string('password');
            $table->string('photo_path')->nullable();
            $table->string('avatar_key', 16)->nullable();
            $table->dateTime('email_verified_at', 6)->nullable();
            $table->dateTime('profile_completed_at', 6)->nullable();
            $table->dateTime('last_seen_at', 6)->nullable();
            $table->dateTime('consent_at', 6);
            $table->timestamps(6);

            $table->unique(['auctioneer_id', 'email']);
            $table->unique(['auctioneer_id', 'cpf_hash']);
        });

        Schema::create('bidder_password_resets', function (Blueprint $table) {
            $table->foreignId('bidder_id')->primary()->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64);
            $table->dateTime('created_at', 6);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->dateTime('created_at', 6);
            $table->primary(['bidder_id', 'product_id']);
        });

        Schema::create('consignment_requests', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->string('phone', 11)->nullable();
            $table->text('description');
            $table->json('photos')->nullable();
            $table->string('status', 12)->default('new');
            $table->timestamps(6);

            $table->index(['auctioneer_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['consignment_requests', 'favorites', 'bidder_password_resets', 'bidders', 'product_view_daily', 'product_photos', 'products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
