<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_domains', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('domain', 253)->unique();
            $table->string('domain_type', 32)->index();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 32)->index();
            $table->string('ssl_status', 32)->index();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX store_domains_one_primary_per_store ON store_domains (store_id) WHERE is_primary');

        Schema::create('store_settings', function (Blueprint $table): void {
            $table->foreignId('store_id')->primary()->constrained('stores')->cascadeOnDelete();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('weight_unit', 16)->default('kg');
            $table->boolean('storefront_enabled')->default(true);
            $table->boolean('password_enabled')->default(false);
            $table->string('password_hash')->nullable();
            $table->string('order_number_prefix', 32)->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('favicon_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->jsonb('social_links')->default('{}');
            $table->jsonb('extra_settings')->default('{}');
            $table->timestampsTz();
        });

        Schema::create('store_themes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('template_key', 120)->index();
            $table->boolean('is_active')->default(false);
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX store_themes_one_active_per_store ON store_themes (store_id) WHERE is_active');
    }

    public function down(): void
    {
        Schema::dropIfExists('store_themes');
        Schema::dropIfExists('store_settings');
        Schema::dropIfExists('store_domains');
    }
};
