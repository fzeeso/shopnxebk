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
        Schema::create('shared_product_options', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 30)->default('dropdown');
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'shared_product_options_id_store_unique');
            $table->index(['store_id', 'position'], 'shared_product_options_store_position_idx');
        });
        DB::statement('CREATE UNIQUE INDEX shared_product_options_store_name_unique ON shared_product_options (store_id, LOWER(name))');
        DB::statement("ALTER TABLE shared_product_options ADD CONSTRAINT shared_product_options_type_check CHECK (type IN ('dropdown', 'radio_buttons', 'buttons', 'swatches'))");

        Schema::create('shared_product_option_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('option_id');
            $table->string('locale', 35);
            $table->string('display_name', 100);
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->unique(['option_id', 'locale'], 'shared_product_option_translations_option_locale_unique');
            $table->foreign(['option_id', 'store_id'], 'shared_product_option_translations_option_store_fk')
                ->references(['id', 'store_id'])->on('shared_product_options')->cascadeOnDelete();
        });

        Schema::create('shared_product_option_values', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('option_id');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'shared_product_option_values_id_store_unique');
            $table->unique(['id', 'option_id', 'store_id'], 'shared_product_option_values_option_store_unique');
            $table->index(['store_id', 'option_id', 'position'], 'shared_product_option_values_store_option_position_idx');
            $table->foreign(['option_id', 'store_id'], 'shared_product_option_values_option_store_fk')
                ->references(['id', 'store_id'])->on('shared_product_options')->cascadeOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX shared_product_option_values_one_default ON shared_product_option_values (store_id, option_id) WHERE is_default');

        Schema::create('shared_product_option_value_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('option_value_id');
            $table->string('locale', 35);
            $table->string('display_label', 100);
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->unique(['option_value_id', 'locale'], 'shared_product_option_value_translations_value_locale_unique');
            $table->foreign(['option_value_id', 'store_id'], 'shared_product_option_value_translations_value_store_fk')
                ->references(['id', 'store_id'])->on('shared_product_option_values')->cascadeOnDelete();
        });

        Schema::create('product_shared_option_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('option_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'product_shared_option_assignments_id_store_unique');
            $table->unique(['store_id', 'product_id', 'option_id'], 'product_shared_option_assignments_store_product_option_unique');
            $table->index(['store_id', 'product_id', 'position'], 'product_shared_option_assignments_store_product_position_idx');
            $table->foreign(['product_id', 'store_id'], 'product_shared_option_assignments_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
            $table->foreign(['option_id', 'store_id'], 'product_shared_option_assignments_option_store_fk')
                ->references(['id', 'store_id'])->on('shared_product_options')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_shared_option_assignments');
        Schema::dropIfExists('shared_product_option_value_translations');
        Schema::dropIfExists('shared_product_option_values');
        Schema::dropIfExists('shared_product_option_translations');
        Schema::dropIfExists('shared_product_options');
    }
};
