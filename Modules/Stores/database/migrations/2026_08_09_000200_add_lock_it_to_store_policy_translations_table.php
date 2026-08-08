<?php

declare(strict_types=1);

use App\Support\Translations\TranslationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_policy_translations', function (Blueprint $table): void {
            TranslationSchema::addLock($table);
        });
    }

    public function down(): void
    {
        Schema::table('store_policy_translations', function (Blueprint $table): void {
            $table->dropColumn('lock_it');
        });
    }
};
