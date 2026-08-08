<?php

declare(strict_types=1);

use App\Support\Translations\TranslationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'brand_translations',
        'collection_translations',
        'category_translations',
        'custom_field_definition_translations',
        'custom_field_option_translations',
        'product_translations',
        'product_option_translations',
        'product_option_value_translations',
        'product_variant_translations',
        'product_image_translations',
        'product_digital_asset_translations',
        'product_custom_field_value_translations',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                TranslationSchema::addLock($table);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('lock_it');
            });
        }
    }
};
