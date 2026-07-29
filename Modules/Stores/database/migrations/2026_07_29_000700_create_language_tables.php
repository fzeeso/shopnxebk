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
        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->string('native_name', 100);
            $table->string('locale', 10)->unique();
            $table->enum('direction', ['ltr', 'rtl'])->default('ltr');
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('store_languages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['store_id', 'language_id']);
            $table->index(['store_id', 'is_active']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX store_languages_one_default_per_store '
            .'ON store_languages (store_id) WHERE is_default = true',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('store_languages');
        Schema::dropIfExists('languages');
    }
};
