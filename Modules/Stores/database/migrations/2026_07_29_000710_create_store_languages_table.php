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
        if (Schema::hasTable('store_languages')) {
            return;
        }

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
        // No-op for upgrade safety. The original language migration owns the
        // destructive rollback because existing installations already had
        // this table before this migration was recorded.
    }
};
