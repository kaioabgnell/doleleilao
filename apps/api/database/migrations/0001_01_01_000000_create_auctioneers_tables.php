<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade e loja do leiloeiro (specs/05-modelo-de-dados.md#identidade--loja).
 * Datas em UTC com microssegundos. CPF/CNPJ criptografado + hash para unicidade (RNF-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctioneers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('company_name', 120);
            $table->string('display_name', 24);
            $table->string('slug', 40)->unique();
            $table->dateTime('slug_changed_at', 6)->nullable();
            $table->string('document_type', 4);
            $table->text('document_enc');
            $table->char('document_hash', 64)->unique();
            $table->string('status', 16)->default('pending');
            $table->dateTime('onboarding_completed_at', 6)->nullable();
            $table->dateTime('onboarding_dismissed_at', 6)->nullable();
            $table->timestamps(6);
        });

        Schema::create('auctioneer_users', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->string('phone', 11);
            $table->string('password');
            $table->string('role', 16)->default('owner');
            $table->dateTime('email_verified_at', 6)->nullable();
            $table->dateTime('terms_accepted_at', 6);
            $table->timestamps(6);
        });

        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_slug', 40)->unique();
            $table->foreignId('auctioneer_id')->constrained()->cascadeOnDelete();
            $table->dateTime('expires_at', 6)->index();
            $table->timestamps(6);
        });

        Schema::create('storefront_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auctioneer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('template', 16)->nullable();
            $table->string('primary_color_key', 16)->nullable();
            $table->string('accent_color_key', 16)->nullable();
            $table->string('background_key', 16)->nullable();
            $table->string('heading_font_key', 16)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('logo_kind', 4)->default('none');
            $table->string('contact_whatsapp', 11)->nullable();
            $table->string('contact_email')->nullable();
            $table->json('socials')->nullable();
            $table->timestamps(6);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('storefront_settings');
        Schema::dropIfExists('slug_redirects');
        Schema::dropIfExists('auctioneer_users');
        Schema::dropIfExists('auctioneers');
    }
};
