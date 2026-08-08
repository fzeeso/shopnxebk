<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table): void {
            $table->boolean('auto_store_translation_flag')->default(false);
            $table->boolean('is_searchable_on_platform_flag')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_store_translation_flag',
                'is_searchable_on_platform_flag',
            ]);
        });
    }
};
