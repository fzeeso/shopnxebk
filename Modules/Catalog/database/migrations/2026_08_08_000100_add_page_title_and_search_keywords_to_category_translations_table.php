<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_translations', function (Blueprint $table): void {
            $table->string('page_title')->nullable()->after('seo_description');
            $table->text('search_keywords')->nullable()->after('page_title');
        });
    }

    public function down(): void
    {
        Schema::table('category_translations', function (Blueprint $table): void {
            $table->dropColumn(['page_title', 'search_keywords']);
        });
    }
};
