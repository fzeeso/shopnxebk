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
        if (DB::table('products')->whereNotNull('product_type')->exists()) {
            throw new RuntimeException(
                'Cannot replace products.product_type while legacy values exist. Map them to product_type_id before rerunning this migration.',
            );
        }
        if (DB::table('product_types')->whereNotNull('platform_taxonomy_node_id')->exists()) {
            throw new RuntimeException(
                'Cannot constrain product_types.platform_taxonomy_node_id while opaque legacy values exist. Clear or map them before rerunning this migration.',
            );
        }

        Schema::create('platform_taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('code', 100);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();
            $table->unique(['code', 'version'], 'platform_taxonomies_code_version_unique');
            $table->index(['status', 'is_default'], 'platform_taxonomies_status_default_idx');
        });

        Schema::create('platform_taxonomy_nodes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('taxonomy_id')->constrained('platform_taxonomies')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('code', 100);
            $table->unsignedSmallInteger('level')->default(0);
            $table->string('path', 500);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'taxonomy_id'], 'platform_taxonomy_nodes_id_taxonomy_unique');
            $table->unique(['taxonomy_id', 'code'], 'platform_taxonomy_nodes_taxonomy_code_unique');
            $table->unique(['taxonomy_id', 'path'], 'platform_taxonomy_nodes_taxonomy_path_unique');
            $table->index(
                ['taxonomy_id', 'parent_id', 'position'],
                'platform_taxonomy_nodes_taxonomy_parent_position_idx',
            );
            $table->index(
                ['taxonomy_id', 'is_active', 'position'],
                'platform_taxonomy_nodes_taxonomy_active_position_idx',
            );
        });

        DB::statement(
            'ALTER TABLE platform_taxonomy_nodes ADD CONSTRAINT platform_taxonomy_nodes_parent_taxonomy_fk '
            .'FOREIGN KEY (parent_id, taxonomy_id) REFERENCES platform_taxonomy_nodes (id, taxonomy_id) ON DELETE CASCADE',
        );

        Schema::create('platform_taxonomy_custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_node_id')->constrained('platform_taxonomy_nodes')->cascadeOnDelete();
            $table->foreignId('custom_field_definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_variant')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(
                ['taxonomy_node_id', 'custom_field_definition_id'],
                'platform_taxonomy_custom_fields_node_definition_unique',
            );
            $table->index(
                ['taxonomy_node_id', 'position'],
                'platform_taxonomy_custom_fields_node_position_idx',
            );
            $table->index(
                'custom_field_definition_id',
                'platform_taxonomy_custom_fields_definition_idx',
            );
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->foreign('platform_taxonomy_node_id')
                ->references('id')
                ->on('platform_taxonomy_nodes')
                ->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('platform_taxonomy_node_id')
                ->nullable()
                ->constrained('platform_taxonomy_nodes')
                ->nullOnDelete();
            $table->unsignedBigInteger('product_type_id')->nullable();
            $table->index(
                ['store_id', 'platform_taxonomy_node_id'],
                'products_store_platform_taxonomy_node_idx',
            );
            $table->index(['store_id', 'product_type_id'], 'products_store_product_type_idx');
            $table->dropColumn('product_type');
        });

        DB::statement(
            'ALTER TABLE products ADD CONSTRAINT products_product_type_store_fk '
            .'FOREIGN KEY (product_type_id, store_id) REFERENCES product_types (id, store_id) '
            .'ON DELETE SET NULL (product_type_id)',
        );

        DB::statement(
            "ALTER TABLE platform_taxonomies ADD CONSTRAINT platform_taxonomies_status_check CHECK (status IN ('draft', 'active', 'archived'))",
        );
        DB::statement(
            'CREATE UNIQUE INDEX platform_taxonomies_one_default ON platform_taxonomies (is_default) WHERE is_default',
        );
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_store_platform_taxonomy_node_idx');
            $table->dropIndex('products_store_product_type_idx');
            $table->dropForeign('products_product_type_store_fk');
            $table->dropForeign(['platform_taxonomy_node_id']);
            $table->dropColumn(['platform_taxonomy_node_id', 'product_type_id']);
            $table->string('product_type')->nullable();
        });

        Schema::table('product_types', function (Blueprint $table): void {
            $table->dropForeign(['platform_taxonomy_node_id']);
        });

        Schema::dropIfExists('platform_taxonomy_custom_fields');
        Schema::dropIfExists('platform_taxonomy_nodes');
        Schema::dropIfExists('platform_taxonomies');
    }
};
