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
        Schema::table('media', function (Blueprint $table): void {
            // Keep every Spatie Media Library column intact and extend the row into
            // ShopNXe's reusable, Store-owned master asset.
            $table->string('directory')->nullable();
            $table->string('path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('filename')->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration', 12, 3)->nullable();
            $table->string('checksum', 128)->nullable();
            $table->string('perceptual_hash', 128)->nullable();
            $table->text('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->string('status', 20)->default('ready');
            $table->string('visibility', 20)->default('private');
            $table->jsonb('metadata')->nullable();

            $table->unique(['id', 'store_id'], 'media_id_store_unique');
            $table->index(['store_id', 'status'], 'media_store_status_idx');
            $table->index(['store_id', 'mime_type'], 'media_store_mime_idx');
            $table->index(['store_id', 'created_at'], 'media_store_created_idx');
            $table->index('checksum', 'media_checksum_idx');
            $table->index('perceptual_hash', 'media_perceptual_hash_idx');
        });

        // Preserve the exact path used by the pre-existing StorePathGenerator for
        // every old row. New rows opt into the dated directory layout in code.
        DB::statement(<<<'SQL'
            UPDATE media AS m
            SET directory = COALESCE(
                    NULLIF(m.custom_properties #>> '{shopnxe_storage,directory}', ''),
                    'stores/' || s.public_id::text || '/media/' || m.public_id::text
                ),
                path = COALESCE(
                    NULLIF(m.custom_properties #>> '{shopnxe_storage,path}', ''),
                    'stores/' || s.public_id::text || '/media/' || m.public_id::text || '/' || m.file_name
                ),
                original_filename = m.file_name,
                filename = m.file_name,
                extension = CASE
                    WHEN position('.' in m.file_name) > 0 THEN lower(regexp_replace(m.file_name, '^.*\.', ''))
                    ELSE NULL
                END,
                status = 'ready',
                visibility = 'private'
            FROM stores AS s
            WHERE s.id = m.store_id
        SQL);
        DB::statement('ALTER TABLE media ALTER COLUMN path SET NOT NULL');
        DB::statement('ALTER TABLE media ALTER COLUMN original_filename SET NOT NULL');
        DB::statement('ALTER TABLE media ALTER COLUMN filename SET NOT NULL');
        DB::statement("ALTER TABLE media ADD CONSTRAINT media_status_check CHECK (status IN ('pending', 'processing', 'ready', 'failed', 'deleted'))");
        DB::statement("ALTER TABLE media ADD CONSTRAINT media_visibility_check CHECK (visibility IN ('private', 'public'))");

        Schema::create('media_variants', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('variant', 30);
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['media_id', 'variant'], 'media_variants_media_variant_unique');
            $table->index('media_id', 'media_variants_media_idx');
        });
        DB::statement("ALTER TABLE media_variants ADD CONSTRAINT media_variants_variant_check CHECK (variant IN ('thumbnail', 'small', 'medium', 'large', 'original'))");

        Schema::create('product_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('media_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();
            $table->unique(['product_id', 'media_id'], 'product_media_product_media_unique');
            $table->index('product_id', 'product_media_product_idx');
            $table->index('media_id', 'product_media_media_idx');
            $table->foreign(['product_id', 'store_id'], 'product_media_product_store_fk')
                ->references(['id', 'store_id'])->on('products')->cascadeOnDelete();
            $table->foreign(['media_id', 'store_id'], 'product_media_media_store_fk')
                ->references(['id', 'store_id'])->on('media')->cascadeOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX product_media_one_primary_idx ON product_media (product_id) WHERE is_primary = true');

        Schema::create('product_variant_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('media_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['product_variant_id', 'media_id'], 'product_variant_media_variant_media_unique');
            $table->index('product_variant_id', 'product_variant_media_variant_idx');
            $table->index('media_id', 'product_variant_media_media_idx');
            $table->foreign(['product_variant_id', 'store_id'], 'product_variant_media_variant_store_fk')
                ->references(['id', 'store_id'])->on('product_variants')->cascadeOnDelete();
            $table->foreign(['media_id', 'store_id'], 'product_variant_media_media_store_fk')
                ->references(['id', 'store_id'])->on('media')->cascadeOnDelete();
        });

        Schema::create('media_ai_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('operation');
            $table->string('status', 20);
            $table->jsonb('result')->nullable();
            $table->decimal('confidence', 8, 6)->nullable();
            $table->timestampsTz();
            $table->index('media_id', 'media_ai_results_media_idx');
        });

        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('media_id');
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->index('media_id', 'media_usages_media_idx');
            $table->index(
                ['store_id', 'resource_type', 'resource_id'],
                'media_usages_store_resource_idx',
            );
            $table->foreign(['media_id', 'store_id'], 'media_usages_media_store_fk')
                ->references(['id', 'store_id'])->on('media')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_ai_results');
        Schema::dropIfExists('product_variant_media');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('media_variants');

        DB::statement('ALTER TABLE media DROP CONSTRAINT IF EXISTS media_status_check');
        DB::statement('ALTER TABLE media DROP CONSTRAINT IF EXISTS media_visibility_check');

        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique('media_id_store_unique');
            $table->dropIndex('media_store_status_idx');
            $table->dropIndex('media_store_mime_idx');
            $table->dropIndex('media_store_created_idx');
            $table->dropIndex('media_checksum_idx');
            $table->dropIndex('media_perceptual_hash_idx');
            $table->dropColumn([
                'directory',
                'path',
                'original_filename',
                'filename',
                'extension',
                'width',
                'height',
                'duration',
                'checksum',
                'perceptual_hash',
                'alt_text',
                'title',
                'caption',
                'status',
                'visibility',
                'metadata',
            ]);
        });
    }
};
