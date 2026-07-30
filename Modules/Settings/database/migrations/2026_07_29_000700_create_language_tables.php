<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    }

    public function down(): void
    {
        // This join originally shipped in this migration. Keeping its rollback
        // here makes fresh rollbacks work without deleting it when the later
        // Stores ownership migration is rolled back on an upgraded database.
        Schema::dropIfExists('store_languages');
        Schema::dropIfExists('languages');
    }
};
