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
            $table->string('category_template', 120)->nullable()->after('search_keywords');
        });
    }

    public function down(): void
    {
        Schema::table('category_translations', function (Blueprint $table): void {
            $table->dropColumn('category_template');
        });
    }
};
