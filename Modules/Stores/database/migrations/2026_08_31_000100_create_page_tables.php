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
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('page_type', 30)->default('content');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('layout_key', 80)->nullable();
            $table->boolean('is_homepage')->default(false);
            $table->boolean('customers_only')->default(false);
            $table->boolean('seo_enabled')->default(false);
            $table->text('external_url')->nullable();
            $table->text('feed_url')->nullable();
            $table->string('contact_email', 320)->nullable();
            $table->jsonb('contact_fields')->default(DB::raw("'[]'::jsonb"));
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['id', 'store_id'], 'pages_id_store_unique');
            $table->index(['store_id', 'parent_id', 'sort_order'], 'pages_store_parent_sort_index');
            $table->index(['store_id', 'status', 'published_at'], 'pages_store_status_index');
            $table->foreign(['parent_id', 'store_id'], 'pages_parent_store_fk')
                ->references(['id', 'store_id'])->on('pages')->restrictOnDelete();
        });

        Schema::create('page_translations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('page_id');
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->string('title', 250);
            $table->string('slug', 250);
            $table->longText('content')->nullable();
            $table->text('summary')->nullable();
            $table->string('seo_title', 250)->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->text('search_keywords')->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();

            $table->unique(['page_id', 'language_id'], 'page_translations_page_language_unique');
            $table->index(['page_id', 'language_id'], 'page_translations_page_language_index');
            $table->foreign(['page_id', 'store_id'], 'page_translations_page_store_fk')
                ->references(['id', 'store_id'])->on('pages')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE pages ADD CONSTRAINT pages_type_check CHECK (page_type IN ('content', 'contact', 'external_link', 'rss'))");
        DB::statement("ALTER TABLE pages ADD CONSTRAINT pages_status_check CHECK (status IN ('disabled', 'draft', 'published'))");
        DB::statement("ALTER TABLE pages ADD CONSTRAINT pages_publication_check CHECK ((status = 'published' AND published_at IS NOT NULL) OR (status <> 'published' AND published_at IS NULL))");
        DB::statement("ALTER TABLE pages ADD CONSTRAINT pages_contact_fields_check CHECK (jsonb_typeof(contact_fields) = 'array')");
        DB::statement("ALTER TABLE page_translations ADD CONSTRAINT page_translations_title_check CHECK (BTRIM(title) <> '')");
        DB::statement("ALTER TABLE page_translations ADD CONSTRAINT page_translations_slug_check CHECK (slug = LOWER(BTRIM(slug)) AND slug <> '' AND slug !~ '[/\\?#[:space:]]')");
        DB::statement('CREATE UNIQUE INDEX pages_one_homepage_per_store ON pages (store_id) WHERE is_homepage = true');
        DB::statement('CREATE UNIQUE INDEX page_translations_store_language_slug_unique ON page_translations (store_id, language_id, LOWER(slug))');
    }

    public function down(): void
    {
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
    }
};
