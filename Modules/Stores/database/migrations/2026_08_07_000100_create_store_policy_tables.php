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
        Schema::create('policy_types', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('store_policies', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('policy_type_id')->constrained('policy_types')->restrictOnDelete();
            $table->string('title', 255);
            $table->string('slug', 160);
            $table->string('status', 20)->default('draft')->index();
            $table->timestampTz('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['store_id', 'policy_type_id']);
            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'status', 'published_at']);
        });

        Schema::create('store_policy_translations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_policy_id')->constrained('store_policies')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->string('title', 255);
            $table->longText('content');
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->timestampsTz();
            $table->unique(['store_policy_id', 'language_id']);
        });

        Schema::create('policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_policy_id')->constrained('store_policies')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['store_policy_id', 'language_id', 'version']);
            $table->index(['store_policy_id', 'language_id', 'created_at']);
        });

        DB::statement("ALTER TABLE policy_types ADD CONSTRAINT policy_types_code_check CHECK (code ~ '^[a-z][a-z0-9_-]{0,79}$')");
        DB::statement("ALTER TABLE store_policies ADD CONSTRAINT store_policies_status_check CHECK (status IN ('draft', 'published'))");
        DB::statement("ALTER TABLE store_policies ADD CONSTRAINT store_policies_publication_check CHECK ((status = 'published' AND published_at IS NOT NULL) OR (status = 'draft' AND published_at IS NULL))");
        DB::statement('ALTER TABLE policy_versions ADD CONSTRAINT policy_versions_number_check CHECK (version > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_versions');
        Schema::dropIfExists('store_policy_translations');
        Schema::dropIfExists('store_policies');
        Schema::dropIfExists('policy_types');
    }
};
