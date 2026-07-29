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
        if (! Schema::hasColumn('stores', 'legal_name')) {
            Schema::table('stores', function (Blueprint $table): void {
                $table->string('legal_name')->nullable();
                $table->text('description')->nullable();
                $table->string('email')->nullable()->index();
                $table->string('phone', 32)->nullable();
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
            });
        }

        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_business_type_check CHECK (business_type IS NULL OR business_type IN ('ecommerce', 'b2b', 'services', 'digital', 'restaurant', 'marketplace'))");
        DB::statement("ALTER TABLE stores ADD CONSTRAINT stores_status_check CHECK (status IN ('pending', 'active', 'suspended', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_business_type_check');
        DB::statement('ALTER TABLE stores DROP CONSTRAINT IF EXISTS stores_status_check');

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_name',
                'description',
                'email',
                'phone',
                'logo',
                'favicon',
                'cover_image',
                'industry',
                'business_type',
                'plan_id',
                'subscription_id',
                'currency_code',
                'language_code',
                'timezone',
                'country_code',
                'is_verified',
                'is_ai_enabled',
                'is_pos_enabled',
                'is_b2b_enabled',
                'is_marketplace_enabled',
                'launched_at',
                'trial_ends_at',
            ]);
        });
    }
};
