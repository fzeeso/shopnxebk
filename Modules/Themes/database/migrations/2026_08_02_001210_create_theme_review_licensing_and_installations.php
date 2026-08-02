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
        Schema::create('theme_submissions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('theme_version_id')->constrained('theme_versions')->cascadeOnDelete();
            $table->unsignedInteger('submission_number');
            $table->string('status', 30)->index();
            $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('automated_results')->default('{}');
            $table->text('review_notes')->nullable();
            $table->jsonb('rejection_codes')->default('[]');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('review_started_at')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->unique(['theme_version_id', 'submission_number']);
        });

        Schema::create('theme_licenses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('license_type', 30)->index();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('billing_order_item_id')->nullable()->index();
            $table->foreignId('purchased_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('issued_at');
            $table->timestampTz('trial_expires_at')->nullable();
            $table->unsignedBigInteger('transferred_from_license_id')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampsTz();
            $table->foreign('transferred_from_license_id')->references('id')->on('theme_licenses')->nullOnDelete();
        });

        Schema::create('store_themes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained('themes')->restrictOnDelete();
            $table->foreignId('theme_version_id')->constrained('theme_versions')->restrictOnDelete();
            $table->foreignId('theme_license_id')->constrained('theme_licenses')->restrictOnDelete();
            $table->foreignId('parent_store_theme_id')->nullable()->constrained('store_themes')->nullOnDelete();
            $table->foreignId('installed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('status', 20)->index();
            $table->string('installed_from', 30)->index();
            $table->jsonb('settings_data')->default('{}');
            $table->jsonb('template_data')->default('{}');
            $table->text('custom_css')->nullable();
            $table->text('customization_object_key')->nullable();
            $table->unsignedInteger('customization_revision')->default(1);
            $table->timestampTz('installed_at');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("CREATE UNIQUE INDEX theme_licenses_one_current_license ON theme_licenses (store_id, theme_id) WHERE status IN ('trial', 'active')");
        DB::statement("CREATE UNIQUE INDEX store_themes_one_published ON store_themes (store_id) WHERE status = 'published' AND deleted_at IS NULL");
        DB::statement("ALTER TABLE theme_submissions ADD CONSTRAINT theme_submissions_status_check CHECK (status IN ('draft', 'submitted', 'automated_review', 'manual_review', 'changes_requested', 'approved', 'rejected', 'withdrawn'))");
        DB::statement("ALTER TABLE theme_licenses ADD CONSTRAINT theme_licenses_type_check CHECK (license_type IN ('trial', 'free', 'paid', 'custom_owner', 'complimentary'))");
        DB::statement("ALTER TABLE theme_licenses ADD CONSTRAINT theme_licenses_status_check CHECK (status IN ('trial', 'active', 'revoked', 'transferred', 'refunded', 'expired'))");
        DB::statement("ALTER TABLE store_themes ADD CONSTRAINT store_themes_status_check CHECK (status IN ('installing', 'draft', 'published', 'archived', 'failed', 'blocked'))");
        DB::statement("ALTER TABLE store_themes ADD CONSTRAINT store_themes_source_check CHECK (installed_from IN ('marketplace', 'platform', 'custom_upload', 'duplicate', 'version_update', 'admin_assigned'))");

        DB::statement('ALTER TABLE media ALTER COLUMN store_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::table('media')->whereNull('store_id')->delete();
        DB::statement('ALTER TABLE media ALTER COLUMN store_id SET NOT NULL');
        Schema::dropIfExists('store_themes');
        Schema::dropIfExists('theme_licenses');
        Schema::dropIfExists('theme_submissions');
    }
};
