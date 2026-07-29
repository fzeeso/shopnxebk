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
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->string('best_for', 255)->nullable();
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->char('currency_code', 3)->default('USD')->index();
            $table->string('billing_interval', 16)->nullable();
            $table->boolean('is_custom_pricing')->default(false);
            $table->string('status', 16)->default('draft')->index();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('key', 120)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('value_type', 16)->default('boolean');
            $table->string('unit', 32)->nullable();
            $table->boolean('is_addon_eligible')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->restrictOnDelete();
            $table->jsonb('value')->nullable();
            $table->boolean('is_included')->default(true);
            $table->boolean('is_addon')->default(false);
            $table->unsignedBigInteger('addon_price_amount')->nullable();
            $table->char('addon_currency_code', 3)->nullable();
            $table->string('addon_billing_interval', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['plan_id', 'feature_id']);
            $table->index(['plan_id', 'sort_order']);
        });

        DB::statement("ALTER TABLE plans ADD CONSTRAINT plans_interval_check CHECK (billing_interval IS NULL OR billing_interval IN ('month', 'year'))");
        DB::statement("ALTER TABLE plans ADD CONSTRAINT plans_status_check CHECK (status IN ('draft', 'active', 'archived'))");
        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_price_mode_check CHECK ((is_custom_pricing = true AND price_amount IS NULL) OR (is_custom_pricing = false AND price_amount IS NOT NULL AND billing_interval IS NOT NULL))');
        DB::statement("ALTER TABLE features ADD CONSTRAINT features_value_type_check CHECK (value_type IN ('boolean', 'integer', 'decimal', 'text'))");
        DB::statement("ALTER TABLE plan_features ADD CONSTRAINT plan_features_addon_interval_check CHECK (addon_billing_interval IS NULL OR addon_billing_interval IN ('month', 'year'))");
        DB::statement('ALTER TABLE plan_features ADD CONSTRAINT plan_features_addon_price_check CHECK (addon_price_amount IS NULL OR is_addon = true)');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plans');
    }
};
