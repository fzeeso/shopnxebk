<?php

declare(strict_types=1);

use App\Support\Translations\TranslationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('code', 100);
            $table->unsignedBigInteger('platform_taxonomy_node_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->unique(['id', 'store_id'], 'product_types_id_store_unique');
            $table->index(['store_id', 'code'], 'product_types_store_code_idx');
            $table->index(
                ['store_id', 'platform_taxonomy_node_id'],
                'product_types_store_platform_taxonomy_idx'
            );
            $table->index(
                ['store_id', 'is_active', 'sort_order'],
                'product_types_store_active_sort_idx'
            );
        });

        Schema::create('product_type_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_type_id');
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('locale', 35);
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            TranslationSchema::addLock($table);
            $table->timestampsTz();

            $table->unique(
                ['product_type_id', 'locale'],
                'product_type_translations_type_locale_unique'
            );
            $table->unique(
                ['store_id', 'locale', 'slug'],
                'product_type_translations_store_locale_slug_unique'
            );
            $table->foreign(
                ['product_type_id', 'store_id'],
                'product_type_translations_type_store_fk'
            )->references(['id', 'store_id'])->on('product_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_type_translations');
        Schema::dropIfExists('product_types');
    }
};
