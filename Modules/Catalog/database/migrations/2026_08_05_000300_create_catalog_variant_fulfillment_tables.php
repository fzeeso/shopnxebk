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
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->unsignedBigInteger('price_amount_minor');
            $table->unsignedBigInteger('compare_at_price_amount_minor')->nullable();
            $table->unsignedBigInteger('msrp_amount_minor')->nullable();
            $table->unsignedBigInteger('cost_per_item_amount_minor')->nullable();
            $table->char('currency_code', 3);
            $table->integer('inventory_qty')->default(0);
            $table->string('inventory_policy', 20)->default('deny');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->decimal('height', 12, 4)->nullable();
            $table->decimal('width', 12, 4)->nullable();
            $table->decimal('depth', 12, 4)->nullable();
            $table->string('dimension_unit', 10)->default('cm');
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('taxable')->default(true);
            $table->boolean('call_for_price')->default(false);
            $table->unsignedBigInteger('image_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'product_variants_id_store_unique');
            $table->unique(['id', 'product_id', 'store_id'], 'product_variants_product_store_unique');
            $table->index(['store_id', 'product_id', 'position'], 'product_variants_store_product_idx');
            $table->foreign(['product_id', 'store_id'], 'product_variants_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('product_variant_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id');
            $table->string('locale', 35);
            $table->string('title')->nullable();
            $table->timestampsTz();
            $table->primary(['variant_id', 'locale']);
            $table->foreign(['variant_id', 'store_id'], 'product_variant_translations_variant_store_fk')
                ->references(['id', 'store_id'])->on('product_variants')->cascadeOnDelete();
        });

        Schema::create('variant_option_values', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('option_value_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['variant_id', 'option_value_id']);
            $table->index(['store_id', 'product_id'], 'variant_option_values_store_product_idx');
            $table->foreign(['variant_id', 'product_id', 'store_id'], 'variant_option_values_variant_store_fk')
                ->references(['id', 'product_id', 'store_id'])->on('product_variants')->cascadeOnDelete();
            $table->foreign(['option_value_id', 'product_id', 'store_id'], 'variant_option_values_option_store_fk')
                ->references(['id', 'product_id', 'store_id'])->on('product_option_values')->cascadeOnDelete();
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('url', 500);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'product_images_id_store_unique');
            $table->unique(['id', 'product_id', 'store_id'], 'product_images_product_store_unique');
            $table->index(['store_id', 'product_id', 'position'], 'product_images_store_product_idx');
            $table->index(['store_id', 'variant_id'], 'product_images_store_variant_idx');
            $table->foreign(['product_id', 'store_id'], 'product_images_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE product_images ADD CONSTRAINT product_images_variant_store_fk FOREIGN KEY (variant_id, product_id, store_id) REFERENCES product_variants (id, product_id, store_id) ON DELETE SET NULL (variant_id)');

        Schema::create('product_image_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('image_id');
            $table->string('locale', 35);
            $table->string('alt_text')->nullable();
            $table->timestampsTz();
            $table->primary(['image_id', 'locale']);
            $table->foreign(['image_id', 'store_id'], 'product_image_translations_image_store_fk')
                ->references(['id', 'store_id'])->on('product_images')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_image_store_fk FOREIGN KEY (image_id, product_id, store_id) REFERENCES product_images (id, product_id, store_id) ON DELETE SET NULL (image_id)');

        Schema::create('product_digital_assets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('file_url', 500);
            $table->string('file_name');
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('file_type', 50)->nullable();
            $table->unsignedInteger('download_limit')->nullable();
            $table->unsignedInteger('link_expires_after_days')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'product_digital_assets_id_store_unique');
            $table->index(['store_id', 'product_id', 'position'], 'product_digital_assets_store_product_idx');
            $table->index(['store_id', 'variant_id'], 'product_digital_assets_store_variant_idx');
            $table->foreign(['product_id', 'store_id'], 'product_digital_assets_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE product_digital_assets ADD CONSTRAINT product_digital_assets_variant_store_fk FOREIGN KEY (variant_id, product_id, store_id) REFERENCES product_variants (id, product_id, store_id) ON DELETE SET NULL (variant_id)');

        Schema::create('product_digital_asset_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('digital_asset_id');
            $table->string('locale', 35);
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->primary(['digital_asset_id', 'locale']);
            $table->foreign(['digital_asset_id', 'store_id'], 'product_digital_asset_translations_store_fk')
                ->references(['id', 'store_id'])->on('product_digital_assets')->cascadeOnDelete();
        });

        Schema::create('product_license_keys', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('key_code');
            $table->string('status', 20)->default('available');
            $table->unsignedInteger('max_activations')->default(1);
            $table->unsignedBigInteger('assigned_to_order_id')->nullable()->index();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'product_license_keys_id_store_unique');
            $table->unique(['store_id', 'key_code'], 'product_license_keys_store_code_unique');
            $table->index(['store_id', 'product_id', 'status'], 'product_license_keys_store_product_idx');
            $table->index(['store_id', 'variant_id', 'status'], 'product_license_keys_store_variant_idx');
            $table->foreign(['product_id', 'store_id'], 'product_license_keys_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE product_license_keys ADD CONSTRAINT product_license_keys_variant_store_fk FOREIGN KEY (variant_id, product_id, store_id) REFERENCES product_variants (id, product_id, store_id) ON DELETE SET NULL (variant_id)');

        DB::statement('CREATE UNIQUE INDEX product_variants_store_sku_unique ON product_variants (store_id, sku) WHERE sku IS NOT NULL');
        DB::statement("ALTER TABLE product_variants ADD CONSTRAINT product_variants_inventory_policy_check CHECK (inventory_policy IN ('deny', 'continue'))");
        DB::statement("ALTER TABLE product_variants ADD CONSTRAINT product_variants_currency_check CHECK (currency_code ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_prices_check CHECK (price_amount_minor >= 0 AND (compare_at_price_amount_minor IS NULL OR compare_at_price_amount_minor >= 0) AND (msrp_amount_minor IS NULL OR msrp_amount_minor >= 0) AND (cost_per_item_amount_minor IS NULL OR cost_per_item_amount_minor >= 0))');
        DB::statement("ALTER TABLE product_license_keys ADD CONSTRAINT product_license_keys_status_check CHECK (status IN ('available', 'assigned', 'revoked', 'expired'))");
        DB::statement('ALTER TABLE product_license_keys ADD CONSTRAINT product_license_keys_activations_check CHECK (max_activations > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_license_keys');
        Schema::dropIfExists('product_digital_asset_translations');
        Schema::dropIfExists('product_digital_assets');
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropForeign('product_variants_image_store_fk'));
        Schema::dropIfExists('product_image_translations');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('variant_option_values');
        Schema::dropIfExists('product_variant_translations');
        Schema::dropIfExists('product_variants');
    }
};
