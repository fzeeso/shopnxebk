<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->text('description')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable();
            $table->string('slug')->unique();
            $table->string('status')->index();
            $table->string('primary_domain')->nullable()->unique();
            $table->string('logo', 2048)->nullable();
            $table->string('favicon', 2048)->nullable();
            $table->string('cover_image', 2048)->nullable();
            $table->string('industry', 120)->nullable();
            $table->string('business_type', 32)->nullable()->index();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->char('currency_code', 3)->default('USD');
            $table->string('language_code', 35)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $table->char('country_code', 2)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_ai_enabled')->default(false);
            $table->boolean('is_pos_enabled')->default(false);
            $table->boolean('is_b2b_enabled')->default(false);
            $table->boolean('is_marketplace_enabled')->default(false);
            $table->timestampTz('launched_at')->nullable();
            $table->timestampTz('trial_ends_at')->nullable()->index();
            $table->jsonb('settings')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();
        });

        Schema::create('store_memberships', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->index();
            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();
            $table->unique(['store_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_memberships');
        Schema::dropIfExists('stores');
    }
};
