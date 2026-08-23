<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['is_active', 'sort_order'], 'fulfillment_types_active_sort_idx');
        });

        Schema::create('fulfillment_type_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fulfillment_type_id')
                ->constrained('fulfillment_types')
                ->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->text('description')->nullable();

            $table->unique(
                ['fulfillment_type_id', 'locale'],
                'fulfillment_type_translations_type_locale_unique'
            );
            $table->foreign('locale', 'fulfillment_type_translations_locale_fk')
                ->references('locale')
                ->on('languages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_type_translations');
        Schema::dropIfExists('fulfillment_types');
    }
};
