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
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'brands_id_store_unique');
            $table->index(['store_id', 'is_active', 'sort_order'], 'brands_store_active_sort_idx');
        });

        Schema::create('brand_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('brand_id');
            $table->string('locale', 35);
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestampsTz();
            $table->primary(['brand_id', 'locale']);
            $table->unique(['store_id', 'locale', 'slug'], 'brand_translations_store_locale_slug_unique');
            $table->foreign(['brand_id', 'store_id'], 'brand_translations_brand_store_fk')
                ->references(['id', 'store_id'])->on('brands')->cascadeOnDelete();
        });

        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('collection_type', 20)->default('manual');
            $table->string('rules_match_type', 10)->default('all');
            $table->text('ai_prompt')->nullable();
            $table->string('ai_model', 100)->nullable();
            $table->string('ai_status', 20)->nullable();
            $table->timestampTz('ai_last_run_at')->nullable();
            $table->text('ai_error_message')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'collections_id_store_unique');
            $table->index(['store_id', 'collection_type'], 'collections_store_type_idx');
            $table->index(['store_id', 'parent_id', 'sort_order'], 'collections_store_parent_sort_idx');
        });

        DB::statement('ALTER TABLE collections ADD CONSTRAINT collections_parent_store_fk FOREIGN KEY (parent_id, store_id) REFERENCES collections (id, store_id) ON DELETE SET NULL (parent_id)');

        Schema::create('collection_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('collection_id');
            $table->string('locale', 35);
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestampsTz();
            $table->primary(['collection_id', 'locale']);
            $table->unique(['store_id', 'locale', 'slug'], 'collection_translations_store_locale_slug_unique');
            $table->foreign(['collection_id', 'store_id'], 'collection_translations_collection_store_fk')
                ->references(['id', 'store_id'])->on('collections')->cascadeOnDelete();
        });

        Schema::create('collection_rules', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('collection_id');
            $table->string('field', 50);
            $table->string('operator', 20);
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'collection_rules_id_store_unique');
            $table->index(['store_id', 'collection_id', 'position'], 'collection_rules_store_collection_idx');
            $table->foreign(['collection_id', 'store_id'], 'collection_rules_collection_store_fk')
                ->references(['id', 'store_id'])->on('collections')->cascadeOnDelete();
        });

        Schema::create('collection_ai_jobs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('collection_id');
            $table->text('prompt');
            $table->string('model', 100);
            $table->string('status', 20)->default('pending');
            $table->jsonb('result_rules')->nullable();
            $table->unsignedInteger('matched_count')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'collection_ai_jobs_id_store_unique');
            $table->index(['store_id', 'collection_id', 'created_at'], 'collection_ai_jobs_store_collection_idx');
            $table->foreign(['collection_id', 'store_id'], 'collection_ai_jobs_collection_store_fk')
                ->references(['id', 'store_id'])->on('collections')->cascadeOnDelete();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'categories_id_store_unique');
            $table->index(['store_id', 'parent_id', 'sort_order'], 'categories_store_parent_sort_idx');
            $table->index(['store_id', 'is_active'], 'categories_store_active_idx');
        });

        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_parent_store_fk FOREIGN KEY (parent_id, store_id) REFERENCES categories (id, store_id) ON DELETE SET NULL (parent_id)');

        Schema::create('category_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('category_id');
            $table->string('locale', 35);
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestampsTz();
            $table->primary(['category_id', 'locale']);
            $table->unique(['store_id', 'locale', 'slug'], 'category_translations_store_locale_slug_unique');
            $table->foreign(['category_id', 'store_id'], 'category_translations_category_store_fk')
                ->references(['id', 'store_id'])->on('categories')->cascadeOnDelete();
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'tags_id_store_unique');
            $table->unique(['store_id', 'name'], 'tags_store_name_unique');
        });

        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('product_type')->nullable();
            $table->string('field_key', 100);
            $table->string('field_type', 20);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'custom_field_definitions_id_store_unique');
            $table->unique(['store_id', 'field_key'], 'custom_field_definitions_store_key_unique');
            $table->index(['store_id', 'product_type'], 'custom_field_definitions_store_type_idx');
        });

        Schema::create('custom_field_definition_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('definition_id');
            $table->string('locale', 35);
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->timestampsTz();
            $table->primary(['definition_id', 'locale']);
            $table->foreign(['definition_id', 'store_id'], 'custom_field_definition_translations_store_fk')
                ->references(['id', 'store_id'])->on('custom_field_definitions')->cascadeOnDelete();
        });

        Schema::create('custom_field_options', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('definition_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'custom_field_options_id_store_unique');
            $table->unique(['id', 'definition_id', 'store_id'], 'custom_field_options_definition_store_unique');
            $table->index(['store_id', 'definition_id', 'position'], 'custom_field_options_store_definition_idx');
            $table->foreign(['definition_id', 'store_id'], 'custom_field_options_definition_store_fk')
                ->references(['id', 'store_id'])->on('custom_field_definitions')->cascadeOnDelete();
        });

        Schema::create('custom_field_option_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('option_id');
            $table->string('locale', 35);
            $table->string('label');
            $table->timestampsTz();
            $table->primary(['option_id', 'locale']);
            $table->foreign(['option_id', 'store_id'], 'custom_field_option_translations_store_fk')
                ->references(['id', 'store_id'])->on('custom_field_options')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE collections ADD CONSTRAINT collections_type_check CHECK (collection_type IN ('manual', 'rule_based', 'ai_generated'))");
        DB::statement("ALTER TABLE collections ADD CONSTRAINT collections_rules_match_check CHECK (rules_match_type IN ('all', 'any'))");
        DB::statement("ALTER TABLE collections ADD CONSTRAINT collections_ai_status_check CHECK (ai_status IS NULL OR ai_status IN ('pending', 'processing', 'completed', 'failed'))");
        DB::statement("ALTER TABLE collection_ai_jobs ADD CONSTRAINT collection_ai_jobs_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
        DB::statement("ALTER TABLE custom_field_definitions ADD CONSTRAINT custom_field_definitions_type_check CHECK (field_type IN ('text', 'number', 'boolean', 'select', 'multi_select', 'date', 'url'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_option_translations');
        Schema::dropIfExists('custom_field_options');
        Schema::dropIfExists('custom_field_definition_translations');
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('collection_ai_jobs');
        Schema::dropIfExists('collection_rules');
        Schema::dropIfExists('collection_translations');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('brand_translations');
        Schema::dropIfExists('brands');
    }
};
