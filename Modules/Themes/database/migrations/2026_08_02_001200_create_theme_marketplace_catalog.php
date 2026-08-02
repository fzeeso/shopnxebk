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
        Schema::dropIfExists('store_themes');

        Schema::create('theme_publishers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('publisher_type', 20)->index();
            $table->string('display_name', 160);
            $table->string('slug', 100)->unique();
            $table->string('status', 20)->index();
            $table->string('support_email', 254);
            $table->text('support_url')->nullable();
            $table->text('website_url')->nullable();
            $table->string('payout_account_reference')->nullable();
            $table->unsignedInteger('default_commission_bps')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('terms_accepted_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('theme_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('theme_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->string('category_type', 20)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('themes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('publisher_id')->nullable()->constrained('theme_publishers')->nullOnDelete();
            $table->foreignId('owner_store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 120)->unique();
            $table->string('summary', 320);
            $table->text('description')->nullable();
            $table->string('source_type', 20)->index();
            $table->string('visibility', 20)->index();
            $table->string('commercial_type', 20)->index();
            $table->string('status', 30)->index();
            $table->unsignedBigInteger('price_amount_minor')->nullable();
            $table->char('price_currency', 3)->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->string('support_email', 254)->nullable();
            $table->text('support_url')->nullable();
            $table->text('documentation_url')->nullable();
            $table->text('demo_url')->nullable();
            $table->jsonb('listing_metadata')->default('{}');
            $table->boolean('is_featured')->default(false)->index();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('theme_category_assignments', function (Blueprint $table): void {
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('theme_categories')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['theme_id', 'category_id']);
        });

        Schema::create('theme_versions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->string('version', 30);
            $table->string('status', 30)->index();
            $table->string('engine_version', 30);
            $table->string('minimum_platform_version', 30)->nullable();
            $table->string('maximum_platform_version', 30)->nullable();
            $table->text('source_archive_object_key');
            $table->text('compiled_artifact_object_key')->nullable();
            $table->char('package_sha256', 64);
            $table->unsignedBigInteger('package_size_bytes');
            $table->unsignedBigInteger('uncompressed_size_bytes');
            $table->unsignedInteger('file_count');
            $table->jsonb('manifest');
            $table->jsonb('settings_schema')->default('[]');
            $table->jsonb('validation_report')->default('{}');
            $table->text('release_notes')->nullable();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->unique(['theme_id', 'version']);
        });

        Schema::table('themes', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('theme_versions')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX themes_one_primary_category ON theme_category_assignments (theme_id) WHERE is_primary');
        DB::statement("ALTER TABLE theme_publishers ADD CONSTRAINT theme_publishers_type_check CHECK (publisher_type IN ('platform', 'third_party'))");
        DB::statement("ALTER TABLE theme_publishers ADD CONSTRAINT theme_publishers_status_check CHECK (status IN ('pending', 'active', 'suspended', 'rejected', 'closed'))");
        DB::statement('ALTER TABLE theme_publishers ADD CONSTRAINT theme_publishers_commission_check CHECK (default_commission_bps IS NULL OR default_commission_bps <= 10000)');
        DB::statement("ALTER TABLE theme_categories ADD CONSTRAINT theme_categories_type_check CHECK (category_type IN ('industry', 'style', 'feature', 'catalog_size'))");
        DB::statement("ALTER TABLE themes ADD CONSTRAINT themes_source_type_check CHECK (source_type IN ('platform', 'third_party', 'custom'))");
        DB::statement("ALTER TABLE themes ADD CONSTRAINT themes_visibility_check CHECK (visibility IN ('public', 'private', 'unlisted'))");
        DB::statement("ALTER TABLE themes ADD CONSTRAINT themes_commercial_type_check CHECK (commercial_type IN ('free', 'paid', 'private'))");
        DB::statement("ALTER TABLE themes ADD CONSTRAINT themes_status_check CHECK (status IN ('draft', 'pending_review', 'approved', 'published', 'suspended', 'rejected', 'retired'))");
        DB::statement("ALTER TABLE themes ADD CONSTRAINT themes_ownership_check CHECK ((source_type = 'custom' AND owner_store_id IS NOT NULL AND publisher_id IS NULL AND visibility = 'private' AND commercial_type = 'private') OR (source_type IN ('platform', 'third_party') AND owner_store_id IS NULL AND publisher_id IS NOT NULL))");
        DB::statement("ALTER TABLE themes ADD CONSTRAINT themes_price_check CHECK ((commercial_type = 'paid' AND price_amount_minor IS NOT NULL AND price_currency IS NOT NULL) OR (commercial_type <> 'paid' AND price_amount_minor IS NULL AND price_currency IS NULL))");
        DB::statement("ALTER TABLE theme_versions ADD CONSTRAINT theme_versions_status_check CHECK (status IN ('uploaded', 'scanning', 'validating', 'validation_failed', 'ready_for_review', 'approved', 'published', 'deprecated', 'blocked'))");
    }

    public function down(): void
    {
        Schema::table('themes', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('theme_versions');
        Schema::dropIfExists('theme_category_assignments');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('theme_categories');
        Schema::dropIfExists('theme_publishers');

        Schema::create('store_themes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('template_key', 120)->index();
            $table->boolean('is_active')->default(false);
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX store_themes_one_active_per_store ON store_themes (store_id) WHERE is_active');
    }
};
