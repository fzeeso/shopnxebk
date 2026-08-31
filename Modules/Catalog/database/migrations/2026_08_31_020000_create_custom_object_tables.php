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
        Schema::create('custom_object_types', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('handle', 150);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['id', 'store_id'], 'custom_object_types_id_store_unique');
            $table->unique(['store_id', 'handle'], 'custom_object_types_store_handle_unique');
            $table->index(['store_id', 'status'], 'custom_object_types_store_status_idx');
        });

        Schema::create('custom_object_type_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('custom_object_type_id');
            $table->string('locale', 35);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->primary(['custom_object_type_id', 'locale'], 'custom_object_type_translations_pk');
            $table->foreign(
                ['custom_object_type_id', 'store_id'],
                'custom_object_type_translations_store_fk',
            )->references(['id', 'store_id'])->on('custom_object_types')->cascadeOnDelete();
        });

        Schema::create('custom_object_fields', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('custom_object_type_id');
            $table->string('handle', 150);
            $table->string('field_type', 30);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_localized')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('reference_object_type_id')->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('validation_rules')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['id', 'store_id'], 'custom_object_fields_id_store_unique');
            $table->unique(
                ['id', 'custom_object_type_id', 'store_id'],
                'custom_object_fields_id_type_store_unique',
            );
            $table->unique(
                ['custom_object_type_id', 'handle'],
                'custom_object_fields_type_handle_unique',
            );
            $table->index(
                ['store_id', 'custom_object_type_id', 'status', 'sort_order'],
                'custom_object_fields_store_type_status_idx',
            );
            $table->index(
                ['store_id', 'reference_object_type_id'],
                'custom_object_fields_reference_type_idx',
            );
            $table->foreign(
                ['custom_object_type_id', 'store_id'],
                'custom_object_fields_owner_store_fk',
            )->references(['id', 'store_id'])->on('custom_object_types')->cascadeOnDelete();
            $table->foreign(
                ['reference_object_type_id', 'store_id'],
                'custom_object_fields_reference_store_fk',
            )->references(['id', 'store_id'])->on('custom_object_types')->restrictOnDelete();
        });

        Schema::create('custom_object_field_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('custom_object_field_id');
            $table->string('locale', 35);
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('help_text')->nullable();
            $table->string('placeholder')->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->primary(['custom_object_field_id', 'locale'], 'custom_object_field_translations_pk');
            $table->foreign(
                ['custom_object_field_id', 'store_id'],
                'custom_object_field_translations_store_fk',
            )->references(['id', 'store_id'])->on('custom_object_fields')->cascadeOnDelete();
        });

        Schema::create('custom_object_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('custom_object_type_id');
            $table->string('handle', 150);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['id', 'store_id'], 'custom_object_entries_id_store_unique');
            $table->unique(
                ['id', 'custom_object_type_id', 'store_id'],
                'custom_object_entries_id_type_store_unique',
            );
            $table->unique(
                ['store_id', 'custom_object_type_id', 'handle'],
                'custom_object_entries_store_type_handle_unique',
            );
            $table->index(
                ['store_id', 'custom_object_type_id', 'status', 'sort_order'],
                'custom_object_entries_store_type_status_idx',
            );
            $table->foreign(
                ['custom_object_type_id', 'store_id'],
                'custom_object_entries_type_store_fk',
            )->references(['id', 'store_id'])->on('custom_object_types')->cascadeOnDelete();
        });

        Schema::create('custom_object_entry_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('custom_object_entry_id');
            $table->string('locale', 35);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->primary(['custom_object_entry_id', 'locale'], 'custom_object_entry_translations_pk');
            $table->index(['store_id', 'locale', 'name'], 'custom_object_entry_translations_search_idx');
            $table->foreign(
                ['custom_object_entry_id', 'store_id'],
                'custom_object_entry_translations_store_fk',
            )->references(['id', 'store_id'])->on('custom_object_entries')->cascadeOnDelete();
        });

        Schema::create('custom_object_values', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('custom_object_type_id');
            $table->unsignedBigInteger('custom_object_entry_id');
            $table->unsignedBigInteger('custom_object_field_id');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 24, 8)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->timestampTz('value_datetime')->nullable();
            $table->jsonb('value_json')->nullable();
            $table->unsignedBigInteger('value_media_id')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'custom_object_values_id_store_unique');
            $table->unique(
                ['custom_object_entry_id', 'custom_object_field_id'],
                'custom_object_values_entry_field_unique',
            );
            $table->index(
                ['store_id', 'custom_object_type_id', 'custom_object_field_id'],
                'custom_object_values_store_type_field_idx',
            );
            $table->foreign(
                ['custom_object_entry_id', 'custom_object_type_id', 'store_id'],
                'custom_object_values_entry_type_store_fk',
            )->references(['id', 'custom_object_type_id', 'store_id'])->on('custom_object_entries')->cascadeOnDelete();
            $table->foreign(
                ['custom_object_field_id', 'custom_object_type_id', 'store_id'],
                'custom_object_values_field_type_store_fk',
            )->references(['id', 'custom_object_type_id', 'store_id'])->on('custom_object_fields')->restrictOnDelete();
            $table->foreign(
                ['value_media_id', 'store_id'],
                'custom_object_values_media_store_fk',
            )->references(['id', 'store_id'])->on('media')->restrictOnDelete();
        });

        Schema::create('custom_object_value_translations', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('custom_object_value_id');
            $table->string('locale', 35);
            $table->text('value_text')->nullable();
            $table->jsonb('value_json')->nullable();
            $table->boolean('lock_it')->default(false);
            $table->timestampsTz();
            $table->primary(['custom_object_value_id', 'locale'], 'custom_object_value_translations_pk');
            $table->foreign(
                ['custom_object_value_id', 'store_id'],
                'custom_object_value_translations_store_fk',
            )->references(['id', 'store_id'])->on('custom_object_values')->cascadeOnDelete();
        });

        Schema::create('custom_object_value_references', function (Blueprint $table): void {
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('custom_object_value_id');
            $table->unsignedBigInteger('custom_object_entry_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->primary(
                ['custom_object_value_id', 'custom_object_entry_id'],
                'custom_object_value_references_pk',
            );
            $table->index(
                ['store_id', 'custom_object_entry_id'],
                'custom_object_value_references_entry_idx',
            );
            $table->foreign(
                ['custom_object_value_id', 'store_id'],
                'custom_object_value_references_value_fk',
            )->references(['id', 'store_id'])->on('custom_object_values')->cascadeOnDelete();
            $table->foreign(
                ['custom_object_entry_id', 'store_id'],
                'custom_object_value_references_entry_fk',
            )->references(['id', 'store_id'])->on('custom_object_entries')->restrictOnDelete();
        });

        Schema::create('custom_object_references', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('custom_field_definition_id');
            $table->unsignedBigInteger('custom_object_type_id');
            $table->unsignedBigInteger('custom_object_entry_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'store_id'], 'custom_object_references_id_store_unique');
            $table->unique(
                ['store_id', 'source_type', 'source_id', 'custom_field_definition_id', 'custom_object_entry_id'],
                'custom_object_references_assignment_unique',
            );
            $table->index(
                ['store_id', 'source_type', 'source_id'],
                'custom_object_references_source_idx',
            );
            $table->index(
                ['store_id', 'custom_field_definition_id'],
                'custom_object_references_definition_idx',
            );
            $table->index(
                ['store_id', 'custom_object_entry_id'],
                'custom_object_references_entry_idx',
            );
            $table->index(
                ['store_id', 'custom_object_type_id'],
                'custom_object_references_type_idx',
            );
            $table->foreign(
                ['custom_field_definition_id', 'store_id'],
                'custom_object_references_definition_fk',
            )->references(['id', 'store_id'])->on('custom_field_definitions')->restrictOnDelete();
            $table->foreign(
                ['custom_object_type_id', 'store_id'],
                'custom_object_references_type_fk',
            )->references(['id', 'store_id'])->on('custom_object_types')->restrictOnDelete();
            $table->foreign(
                ['custom_object_entry_id', 'custom_object_type_id', 'store_id'],
                'custom_object_references_entry_type_fk',
            )->references(['id', 'custom_object_type_id', 'store_id'])->on('custom_object_entries')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE custom_object_types ADD CONSTRAINT custom_object_types_status_check CHECK (status IN ('draft', 'active', 'archived'))");
        DB::statement("ALTER TABLE custom_object_fields ADD CONSTRAINT custom_object_fields_status_check CHECK (status IN ('active', 'archived'))");
        DB::statement("ALTER TABLE custom_object_entries ADD CONSTRAINT custom_object_entries_status_check CHECK (status IN ('draft', 'active', 'archived'))");
        DB::statement("ALTER TABLE custom_object_fields ADD CONSTRAINT custom_object_fields_type_check CHECK (field_type IN ('text', 'textarea', 'rich_text', 'number', 'decimal', 'boolean', 'date', 'datetime', 'url', 'email', 'media', 'image', 'select', 'multi_select', 'object_reference', 'multi_object_reference'))");
        DB::statement("ALTER TABLE custom_object_fields ADD CONSTRAINT custom_object_fields_reference_check CHECK ((field_type IN ('object_reference', 'multi_object_reference') AND reference_object_type_id IS NOT NULL) OR (field_type NOT IN ('object_reference', 'multi_object_reference') AND reference_object_type_id IS NULL))");
        DB::statement('ALTER TABLE custom_object_values ADD CONSTRAINT custom_object_values_scalar_check CHECK (num_nonnulls(value_text, value_number, value_boolean, value_date, value_datetime, value_json, value_media_id) <= 1)');
        DB::statement('ALTER TABLE custom_object_value_translations ADD CONSTRAINT custom_object_value_translations_value_check CHECK (num_nonnulls(value_text, value_json) = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_object_references');
        Schema::dropIfExists('custom_object_value_references');
        Schema::dropIfExists('custom_object_value_translations');
        Schema::dropIfExists('custom_object_values');
        Schema::dropIfExists('custom_object_entry_translations');
        Schema::dropIfExists('custom_object_entries');
        Schema::dropIfExists('custom_object_field_translations');
        Schema::dropIfExists('custom_object_fields');
        Schema::dropIfExists('custom_object_type_translations');
        Schema::dropIfExists('custom_object_types');
    }
};
