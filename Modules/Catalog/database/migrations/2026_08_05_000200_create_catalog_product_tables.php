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
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->string('fulfillment_type', 20)->default('physical');
            $table->boolean('track_inventory')->default(true);
            $table->string('status', 20)->default('draft');
            $table->boolean('has_variants')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'products_id_store_unique');
            $table->index(['store_id', 'status'], 'products_store_status_idx');
            $table->index(['store_id', 'fulfillment_type'], 'products_store_fulfillment_idx');
            $table->index(['store_id', 'brand_id'], 'products_store_brand_idx');
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_brand_store_fk FOREIGN KEY (brand_id, store_id) REFERENCES brands (id, store_id) ON DELETE SET NULL (brand_id)');

        Schema::create('product_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('locale', 35);
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestampsTz();
            $table->primary(['product_id', 'locale']);
            $table->unique(['store_id', 'locale', 'slug'], 'product_translations_store_locale_slug_unique');
            $table->foreign(['product_id', 'store_id'], 'product_translations_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('product_tags', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestampsTz();
            $table->primary(['product_id', 'tag_id']);
            $table->index(['store_id', 'tag_id'], 'product_tags_store_tag_idx');
            $table->foreign(['product_id', 'store_id'], 'product_tags_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
            $table->foreign(['tag_id', 'store_id'], 'product_tags_tag_store_fk')
                ->references(['id', 'store_id'])->on('tags')->cascadeOnDelete();
        });

        Schema::create('product_collections', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('collection_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('added_by', 10)->default('manual');
            $table->boolean('is_pinned')->default(false);
            $table->timestampsTz();
            $table->primary(['collection_id', 'product_id']);
            $table->index(['store_id', 'product_id'], 'product_collections_store_product_idx');
            $table->index(['store_id', 'collection_id', 'sort_order'], 'product_collections_store_sort_idx');
            $table->foreign(['collection_id', 'store_id'], 'product_collections_collection_store_fk')
                ->references(['id', 'store_id'])->on('collections')->cascadeOnDelete();
            $table->foreign(['product_id', 'store_id'], 'product_collections_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('product_categories', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();
            $table->primary(['category_id', 'product_id']);
            $table->index(['store_id', 'product_id'], 'product_categories_store_product_idx');
            $table->index(['store_id', 'category_id', 'sort_order'], 'product_categories_store_sort_idx');
            $table->foreign(['category_id', 'store_id'], 'product_categories_category_store_fk')
                ->references(['id', 'store_id'])->on('categories')->cascadeOnDelete();
            $table->foreign(['product_id', 'store_id'], 'product_categories_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('product_options', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'product_options_id_store_unique');
            $table->unique(['id', 'product_id', 'store_id'], 'product_options_product_store_unique');
            $table->index(['store_id', 'product_id', 'position'], 'product_options_store_product_idx');
            $table->foreign(['product_id', 'store_id'], 'product_options_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('product_option_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('option_id');
            $table->string('locale', 35);
            $table->string('name', 100);
            $table->timestampsTz();
            $table->primary(['option_id', 'locale']);
            $table->foreign(['option_id', 'store_id'], 'product_option_translations_option_store_fk')
                ->references(['id', 'store_id'])->on('product_options')->cascadeOnDelete();
        });

        Schema::create('product_option_values', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('option_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'product_option_values_id_store_unique');
            $table->unique(['id', 'product_id', 'store_id'], 'product_option_values_product_store_unique');
            $table->index(['store_id', 'option_id', 'position'], 'product_option_values_store_option_idx');
            $table->foreign(['option_id', 'product_id', 'store_id'], 'product_option_values_option_store_fk')
                ->references(['id', 'product_id', 'store_id'])->on('product_options')->cascadeOnDelete();
        });

        Schema::create('product_option_value_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('option_value_id');
            $table->string('locale', 35);
            $table->string('value', 100);
            $table->timestampsTz();
            $table->primary(['option_value_id', 'locale']);
            $table->foreign(['option_value_id', 'store_id'], 'product_option_value_translations_store_fk')
                ->references(['id', 'store_id'])->on('product_option_values')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT products_fulfillment_type_check CHECK (fulfillment_type IN ('physical', 'digital', 'software', 'service'))");
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('draft', 'active', 'archived'))");
        DB::statement("ALTER TABLE product_collections ADD CONSTRAINT product_collections_added_by_check CHECK (added_by IN ('manual', 'rule', 'ai'))");
        DB::statement('CREATE UNIQUE INDEX product_categories_one_primary ON product_categories (store_id, product_id) WHERE is_primary');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_value_translations');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_option_translations');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('product_collections');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
    }
};
