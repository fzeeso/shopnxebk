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
        Schema::create('product_modifier_groups', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('code', 100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['id', 'store_id'], 'product_modifier_groups_id_store_unique');
            $table->unique(['id', 'product_id', 'store_id'], 'product_modifier_groups_product_store_unique');
            $table->unique(['product_id', 'code'], 'product_modifier_groups_product_code_unique');
            $table->index(['store_id', 'product_id', 'is_active'], 'product_modifier_groups_store_product_active_idx');
            $table->foreign(['product_id', 'store_id'], 'product_modifier_groups_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('product_modifier_group_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('group_id');
            $table->string('locale', 35);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->unique(['group_id', 'locale'], 'product_modifier_group_translations_group_locale_unique');
            $table->foreign(['group_id', 'store_id'], 'product_modifier_group_translations_group_store_fk')
                ->references(['id', 'store_id'])->on('product_modifier_groups')->cascadeOnDelete();
        });

        Schema::create('product_modifier_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('modifier_id');
            $table->unsignedBigInteger('modifier_group_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required_override')->nullable();
            $table->integer('min_selections_override')->nullable();
            $table->integer('max_selections_override')->nullable();
            $table->jsonb('settings_override')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['id', 'store_id'], 'product_modifier_assignments_id_store_unique');
            $table->unique(['id', 'modifier_id', 'store_id'], 'product_modifier_assignments_modifier_store_unique');
            $table->unique(['product_id', 'modifier_id'], 'product_modifier_assignments_product_modifier_unique');
            $table->index(['store_id', 'product_id', 'is_active'], 'product_modifier_assignments_store_product_active_idx');
            $table->foreign(['product_id', 'store_id'], 'product_modifier_assignments_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
            $table->foreign(['modifier_id', 'store_id'], 'product_modifier_assignments_modifier_store_fk')
                ->references(['id', 'store_id'])->on('modifier_definitions')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE product_modifier_assignments ADD CONSTRAINT product_modifier_assignments_group_product_store_fk FOREIGN KEY (modifier_group_id, product_id, store_id) REFERENCES product_modifier_groups (id, product_id, store_id) ON DELETE SET NULL (modifier_group_id)');
        DB::statement('ALTER TABLE product_modifier_assignments ADD CONSTRAINT product_modifier_assignments_selection_range_check CHECK (min_selections_override IS NULL OR max_selections_override IS NULL OR min_selections_override <= max_selections_override)');

        Schema::create('product_modifier_assignment_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_modifier_assignment_id');
            $table->string('locale', 35);
            $table->string('name_override')->nullable();
            $table->text('description_override')->nullable();
            $table->string('placeholder_override', 500)->nullable();
            $table->text('help_text_override')->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->unique(['product_modifier_assignment_id', 'locale'], 'product_modifier_assignment_translations_assignment_locale_unique');
            $table->foreign(['product_modifier_assignment_id', 'store_id'], 'product_modifier_assignment_translations_assignment_store_fk')
                ->references(['id', 'store_id'])->on('product_modifier_assignments')->cascadeOnDelete();
        });

        Schema::create('product_modifier_value_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_modifier_assignment_id');
            $table->unsignedBigInteger('modifier_id');
            $table->unsignedBigInteger('modifier_value_id');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default_override')->nullable();
            $table->integer('sort_order')->nullable();
            $table->jsonb('settings_override')->nullable();
            $table->timestampsTz();
            $table->unique(['product_modifier_assignment_id', 'modifier_value_id'], 'product_modifier_value_assignments_assignment_value_unique');
            $table->index(['product_modifier_assignment_id', 'is_enabled'], 'product_modifier_value_assignments_assignment_enabled_idx');
            $table->foreign(['product_modifier_assignment_id', 'modifier_id', 'store_id'], 'product_modifier_value_assignments_assignment_modifier_store_fk')
                ->references(['id', 'modifier_id', 'store_id'])->on('product_modifier_assignments')->cascadeOnDelete();
            $table->foreign(['modifier_value_id', 'modifier_id', 'store_id'], 'product_modifier_value_assignments_value_modifier_store_fk')
                ->references(['id', 'modifier_id', 'store_id'])->on('modifier_values')->cascadeOnDelete();
        });

        $this->createOverrideTable('product_modifier_price_overrides', false);
        $this->createOverrideTable('product_modifier_value_price_overrides', true);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_modifier_value_price_overrides');
        Schema::dropIfExists('product_modifier_price_overrides');
        Schema::dropIfExists('product_modifier_value_assignments');
        Schema::dropIfExists('product_modifier_assignment_translations');
        Schema::dropIfExists('product_modifier_assignments');
        Schema::dropIfExists('product_modifier_group_translations');
        Schema::dropIfExists('product_modifier_groups');
    }

    private function createOverrideTable(string $tableName, bool $withValue): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($tableName, $withValue): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_modifier_assignment_id');
            if ($withValue) {
                $table->unsignedBigInteger('modifier_id');
                $table->unsignedBigInteger('modifier_value_id');
            }
            $table->char('currency_code', 3);
            $table->string('adjustment_type', 20);
            $table->decimal('amount', 18, 4);
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $columns = ['store_id', 'product_modifier_assignment_id'];
            if ($withValue) {
                $columns[] = 'modifier_value_id';
            }
            $columns[] = 'currency_code';
            $table->index($columns, "{$tableName}_store_resource_currency_idx");
            $table->index(['store_id', 'channel_id', 'customer_group_id'], "{$tableName}_store_audience_idx");
            $table->foreign('currency_code', "{$tableName}_currency_fk")->references('code')->on('currencies')->restrictOnDelete();
            if ($withValue) {
                $table->foreign(['product_modifier_assignment_id', 'modifier_id', 'store_id'], "{$tableName}_assignment_modifier_store_fk")
                    ->references(['id', 'modifier_id', 'store_id'])->on('product_modifier_assignments')->cascadeOnDelete();
                $table->foreign(['modifier_value_id', 'modifier_id', 'store_id'], "{$tableName}_value_modifier_store_fk")
                    ->references(['id', 'modifier_id', 'store_id'])->on('modifier_values')->cascadeOnDelete();
            } else {
                $table->foreign(['product_modifier_assignment_id', 'store_id'], "{$tableName}_assignment_store_fk")
                    ->references(['id', 'store_id'])->on('product_modifier_assignments')->cascadeOnDelete();
            }
        });
        DB::statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_type_check CHECK (adjustment_type IN ('fixed', 'percentage'))");
        DB::statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_date_range_check CHECK (starts_at IS NULL OR ends_at IS NULL OR starts_at <= ends_at)");
    }
};
