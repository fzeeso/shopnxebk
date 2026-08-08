<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Translations\AutomatedTranslationWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class TranslationLockDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_translation_table_has_a_non_nullable_false_lock_flag(): void
    {
        $tables = array_map(
            static fn (object $row): string => (string) $row->table_name,
            DB::select(<<<'SQL'
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'public'
                  AND table_type = 'BASE TABLE'
                  AND RIGHT(table_name, 13) = '_translations'
                ORDER BY table_name
                SQL),
        );

        self::assertGreaterThanOrEqual(13, count($tables));

        foreach ($tables as $table) {
            self::assertTrue(Schema::hasColumn($table, 'lock_it'), "{$table} must define lock_it.");

            $column = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', $table)
                ->where('column_name', 'lock_it')
                ->firstOrFail();

            self::assertSame('NO', $column->is_nullable, "{$table}.lock_it must be non-nullable.");
            self::assertStringContainsString('false', strtolower((string) $column->column_default));
        }
    }

    public function test_automated_writes_skip_locked_translations_and_update_unlocked_rows(): void
    {
        $store = Store::factory()->create();
        $brandId = DB::table('brands')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'store_id' => $store->getKey(),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('brand_translations')->insert([
            'store_id' => $store->getKey(),
            'brand_id' => $brandId,
            'locale' => 'en',
            'name' => 'Merchant Brand Name',
            'slug' => 'merchant-brand',
            'lock_it' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $writer = app(AutomatedTranslationWriter::class);
        $row = [
            'store_id' => $store->getKey(),
            'brand_id' => $brandId,
            'locale' => 'en',
            'name' => 'System Brand Name',
            'slug' => 'system-brand',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        self::assertSame(0, $writer->upsert(
            'brand_translations',
            [$row],
            ['brand_id', 'locale'],
            ['name', 'slug', 'updated_at'],
        ));
        $this->assertDatabaseHas('brand_translations', [
            'brand_id' => $brandId,
            'locale' => 'en',
            'name' => 'Merchant Brand Name',
            'lock_it' => true,
        ]);

        DB::table('brand_translations')
            ->where('brand_id', $brandId)
            ->where('locale', 'en')
            ->update(['lock_it' => false]);

        self::assertSame(1, $writer->upsert(
            'brand_translations',
            [$row],
            ['brand_id', 'locale'],
            ['name', 'slug', 'updated_at'],
        ));
        $this->assertDatabaseHas('brand_translations', [
            'brand_id' => $brandId,
            'locale' => 'en',
            'name' => 'System Brand Name',
            'lock_it' => false,
        ]);
    }
}
