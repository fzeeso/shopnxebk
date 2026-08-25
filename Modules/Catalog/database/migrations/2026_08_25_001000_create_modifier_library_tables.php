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
        Schema::create('modifier_library_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('code', 100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['id', 'store_id'], 'modifier_library_categories_id_store_unique');
            $table->unique(['store_id', 'code'], 'modifier_library_categories_store_code_unique');
            $table->index(['store_id', 'is_active', 'sort_order'], 'modifier_library_categories_store_active_sort_idx');
        });

        Schema::create('modifier_library_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('category_id');
            $table->string('locale', 35);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->unique(['category_id', 'locale'], 'modifier_library_category_translations_category_locale_unique');
            $table->foreign(['category_id', 'store_id'], 'modifier_library_category_translations_category_store_fk')
                ->references(['id', 'store_id'])->on('modifier_library_categories')->cascadeOnDelete();
        });

        Schema::create('modifier_definitions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('library_category_id')->nullable();
            $table->string('code', 100);
            $table->string('type', 40);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required_default')->default(false);
            $table->boolean('supports_multiple')->default(false);
            $table->integer('min_selections')->nullable();
            $table->integer('max_selections')->nullable();
            $table->integer('sort_order')->default(0);
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['id', 'store_id'], 'modifier_definitions_id_store_unique');
            $table->unique(['store_id', 'code'], 'modifier_definitions_store_code_unique');
            $table->index(['store_id', 'is_active'], 'modifier_definitions_store_active_idx');
            $table->index(['store_id', 'library_category_id', 'sort_order'], 'modifier_definitions_store_category_sort_idx');
        });
        DB::statement('ALTER TABLE modifier_definitions ADD CONSTRAINT modifier_definitions_category_store_fk FOREIGN KEY (library_category_id, store_id) REFERENCES modifier_library_categories (id, store_id) ON DELETE SET NULL (library_category_id)');
        DB::statement("ALTER TABLE modifier_definitions ADD CONSTRAINT modifier_definitions_type_check CHECK (type IN ('select', 'radio', 'buttons', 'swatch', 'checkbox', 'checkbox_group', 'text', 'textarea', 'number', 'date', 'datetime', 'file', 'image_upload', 'toggle'))");
        DB::statement('ALTER TABLE modifier_definitions ADD CONSTRAINT modifier_definitions_selection_range_check CHECK (min_selections IS NULL OR max_selections IS NULL OR min_selections <= max_selections)');
        DB::statement('ALTER TABLE modifier_definitions ADD CONSTRAINT modifier_definitions_single_choice_check CHECK (supports_multiple = true OR COALESCE(max_selections, 1) <= 1)');

        Schema::create('modifier_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('modifier_id');
            $table->string('locale', 35);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('placeholder', 500)->nullable();
            $table->text('help_text')->nullable();
            $table->string('required_message', 500)->nullable();
            $table->string('validation_message', 500)->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->unique(['modifier_id', 'locale'], 'modifier_translations_modifier_locale_unique');
            $table->index(['modifier_id', 'locale'], 'modifier_translations_modifier_locale_idx');
            $table->foreign(['modifier_id', 'store_id'], 'modifier_translations_modifier_store_fk')
                ->references(['id', 'store_id'])->on('modifier_definitions')->cascadeOnDelete();
        });

        Schema::create('modifier_values', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('modifier_id');
            $table->string('code', 100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('colour_value', 50)->nullable();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->string('icon')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['id', 'store_id'], 'modifier_values_id_store_unique');
            $table->unique(['id', 'modifier_id', 'store_id'], 'modifier_values_modifier_store_unique');
            $table->unique(['modifier_id', 'code'], 'modifier_values_modifier_code_unique');
            $table->index(['store_id', 'modifier_id', 'is_active'], 'modifier_values_store_modifier_active_idx');
            $table->foreign(['modifier_id', 'store_id'], 'modifier_values_modifier_store_fk')
                ->references(['id', 'store_id'])->on('modifier_definitions')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE modifier_values ADD CONSTRAINT modifier_values_image_store_fk FOREIGN KEY (image_id, store_id) REFERENCES media (id, store_id) ON DELETE SET NULL (image_id)');

        Schema::create('modifier_value_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('modifier_value_id');
            $table->string('locale', 35);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->unique(['modifier_value_id', 'locale'], 'modifier_value_translations_value_locale_unique');
            $table->index(['modifier_value_id', 'locale'], 'modifier_value_translations_value_locale_idx');
            $table->foreign(['modifier_value_id', 'store_id'], 'modifier_value_translations_value_store_fk')
                ->references(['id', 'store_id'])->on('modifier_values')->cascadeOnDelete();
        });

        Schema::create('modifier_validation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('modifier_id');
            $table->string('rule_type', 50);
            $table->jsonb('rule_value')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'modifier_validation_rules_id_store_unique');
            $table->index(['store_id', 'modifier_id', 'is_active'], 'modifier_validation_rules_store_modifier_active_idx');
            $table->foreign(['modifier_id', 'store_id'], 'modifier_validation_rules_modifier_store_fk')
                ->references(['id', 'store_id'])->on('modifier_definitions')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE modifier_validation_rules ADD CONSTRAINT modifier_validation_rules_type_check CHECK (rule_type IN ('min_length', 'max_length', 'min_number', 'max_number', 'regex', 'allowed_file_extensions', 'max_file_size', 'max_files', 'min_date', 'max_date'))");

        Schema::create('modifier_validation_rule_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('rule_id');
            $table->string('locale', 35);
            $table->string('message', 500);
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->unique(['rule_id', 'locale'], 'modifier_validation_rule_translations_rule_locale_unique');
            $table->foreign(['rule_id', 'store_id'], 'modifier_validation_rule_translations_rule_store_fk')
                ->references(['id', 'store_id'])->on('modifier_validation_rules')->cascadeOnDelete();
        });

        $this->createPriceAdjustmentTable('modifier_price_adjustments', 'modifier_id', 'modifier_definitions');
        $this->createPriceAdjustmentTable('modifier_value_price_adjustments', 'modifier_value_id', 'modifier_values');
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_value_price_adjustments');
        Schema::dropIfExists('modifier_price_adjustments');
        Schema::dropIfExists('modifier_validation_rule_translations');
        Schema::dropIfExists('modifier_validation_rules');
        Schema::dropIfExists('modifier_value_translations');
        Schema::dropIfExists('modifier_values');
        Schema::dropIfExists('modifier_translations');
        Schema::dropIfExists('modifier_definitions');
        Schema::dropIfExists('modifier_library_category_translations');
        Schema::dropIfExists('modifier_library_categories');
    }

    private function createPriceAdjustmentTable(string $tableName, string $foreignColumn, string $foreignTable): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($foreignColumn, $foreignTable, $tableName): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger($foreignColumn);
            $table->char('currency_code', 3);
            $table->string('adjustment_type', 20);
            $table->decimal('amount', 18, 4);
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->index(['store_id', $foreignColumn, 'currency_code'], "{$tableName}_store_resource_currency_idx");
            $table->index(['store_id', 'channel_id', 'customer_group_id'], "{$tableName}_store_audience_idx");
            $table->foreign('currency_code', "{$tableName}_currency_fk")->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign([$foreignColumn, 'store_id'], "{$tableName}_resource_store_fk")
                ->references(['id', 'store_id'])->on($foreignTable)->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_type_check CHECK (adjustment_type IN ('fixed', 'percentage'))");
        DB::statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_date_range_check CHECK (starts_at IS NULL OR ends_at IS NULL OR starts_at <= ends_at)");
    }
};
