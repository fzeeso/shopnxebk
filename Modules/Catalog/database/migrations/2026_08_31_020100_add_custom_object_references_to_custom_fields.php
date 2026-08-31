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
        DB::statement('ALTER TABLE custom_field_definitions DROP CONSTRAINT custom_field_definitions_type_check');

        Schema::table('custom_field_definitions', function (Blueprint $table): void {
            $table->unsignedBigInteger('reference_object_type_id')->nullable();
            $table->foreign(
                ['reference_object_type_id', 'store_id'],
                'custom_field_definitions_object_type_fk',
            )->references(['id', 'store_id'])->on('custom_object_types')->restrictOnDelete();
            $table->index(
                ['store_id', 'reference_object_type_id'],
                'custom_field_definitions_reference_type_idx',
            );
        });

        DB::statement("ALTER TABLE custom_field_definitions ADD CONSTRAINT custom_field_definitions_type_check CHECK (field_type IN ('text', 'number', 'boolean', 'select', 'multi_select', 'date', 'url', 'object_reference', 'multi_object_reference'))");
        DB::statement("ALTER TABLE custom_field_definitions ADD CONSTRAINT custom_field_definitions_reference_check CHECK ((field_type IN ('object_reference', 'multi_object_reference') AND reference_object_type_id IS NOT NULL) OR (field_type NOT IN ('object_reference', 'multi_object_reference') AND reference_object_type_id IS NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE custom_field_definitions DROP CONSTRAINT custom_field_definitions_reference_check');
        DB::statement('ALTER TABLE custom_field_definitions DROP CONSTRAINT custom_field_definitions_type_check');

        Schema::table('custom_field_definitions', function (Blueprint $table): void {
            $table->dropForeign('custom_field_definitions_object_type_fk');
            $table->dropIndex('custom_field_definitions_reference_type_idx');
            $table->dropColumn('reference_object_type_id');
        });

        DB::statement("ALTER TABLE custom_field_definitions ADD CONSTRAINT custom_field_definitions_type_check CHECK (field_type IN ('text', 'number', 'boolean', 'select', 'multi_select', 'date', 'url'))");
    }
};
