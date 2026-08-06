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
        Schema::create('product_custom_field_values', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('definition_id');
            $table->decimal('value_number', 18, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->unsignedBigInteger('value_option_id')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'product_custom_field_values_id_store_unique');
            $table->unique(['id', 'definition_id', 'store_id'], 'product_custom_field_values_definition_store_unique');
            $table->index(['store_id', 'product_id'], 'product_custom_field_values_store_product_idx');
            $table->index(['store_id', 'variant_id'], 'product_custom_field_values_store_variant_idx');
            $table->index(['store_id', 'definition_id'], 'product_custom_field_values_store_definition_idx');
            $table->foreign(['product_id', 'store_id'], 'product_custom_field_values_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
            $table->foreign(['variant_id', 'product_id', 'store_id'], 'product_custom_field_values_variant_store_fk')
                ->references(['id', 'product_id', 'store_id'])->on('product_variants')->cascadeOnDelete();
            $table->foreign(['definition_id', 'store_id'], 'product_custom_field_values_definition_store_fk')
                ->references(['id', 'store_id'])->on('custom_field_definitions')->cascadeOnDelete();
            $table->foreign(['value_option_id', 'definition_id', 'store_id'], 'product_custom_field_values_option_store_fk')
                ->references(['id', 'definition_id', 'store_id'])->on('custom_field_options')->restrictOnDelete();
        });

        Schema::create('product_custom_field_value_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('value_id');
            $table->string('locale', 35);
            $table->text('value_text');
            $table->timestampsTz();
            $table->primary(['value_id', 'locale']);
            $table->foreign(['value_id', 'store_id'], 'product_custom_field_value_translations_store_fk')
                ->references(['id', 'store_id'])->on('product_custom_field_values')->cascadeOnDelete();
        });

        Schema::create('product_custom_field_value_options', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('definition_id');
            $table->unsignedBigInteger('value_id');
            $table->unsignedBigInteger('option_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['value_id', 'option_id']);
            $table->index(['store_id', 'definition_id'], 'product_custom_field_value_options_store_idx');
            $table->foreign(['value_id', 'definition_id', 'store_id'], 'product_custom_field_value_options_value_store_fk')
                ->references(['id', 'definition_id', 'store_id'])->on('product_custom_field_values')->cascadeOnDelete();
            $table->foreign(['option_id', 'definition_id', 'store_id'], 'product_custom_field_value_options_option_store_fk')
                ->references(['id', 'definition_id', 'store_id'])->on('custom_field_options')->cascadeOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX product_custom_field_values_scope_unique ON product_custom_field_values (store_id, definition_id, product_id, COALESCE(variant_id, 0))');
        DB::statement('ALTER TABLE product_custom_field_values ADD CONSTRAINT product_custom_field_values_scalar_check CHECK (num_nonnulls(value_number, value_boolean, value_date, value_option_id) <= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_custom_field_value_options');
        Schema::dropIfExists('product_custom_field_value_translations');
        Schema::dropIfExists('product_custom_field_values');
    }
};
