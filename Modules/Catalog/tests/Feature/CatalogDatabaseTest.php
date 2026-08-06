<?php

declare(strict_types=1);

namespace Modules\Catalog\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class CatalogDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_catalog_schema_is_registered(): void
    {
        $tables = [
            'brands',
            'brand_translations',
            'collections',
            'collection_translations',
            'collection_rules',
            'collection_ai_jobs',
            'categories',
            'category_translations',
            'tags',
            'products',
            'product_translations',
            'product_tags',
            'product_collections',
            'product_categories',
            'product_options',
            'product_option_translations',
            'product_option_values',
            'product_option_value_translations',
            'product_variants',
            'product_variant_translations',
            'variant_option_values',
            'product_images',
            'product_image_translations',
            'product_digital_assets',
            'product_digital_asset_translations',
            'product_license_keys',
            'custom_field_definitions',
            'custom_field_definition_translations',
            'custom_field_options',
            'custom_field_option_translations',
            'product_custom_field_values',
            'product_custom_field_value_translations',
            'product_custom_field_value_options',
        ];

        foreach ($tables as $table) {
            self::assertTrue(Schema::hasTable($table), "Missing catalog table: {$table}");
        }

        self::assertTrue(Schema::hasColumns('product_variants', [
            'id',
            'public_id',
            'store_id',
            'product_id',
            'price_amount_minor',
            'currency_code',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_localized_slugs_are_unique_per_store_and_locale(): void
    {
        $firstStore = Store::factory()->create();
        $secondStore = Store::factory()->create();
        $firstCategoryId = $this->createCategory($firstStore);
        $secondCategoryId = $this->createCategory($secondStore);

        DB::table('category_translations')->insert([
            [
                'store_id' => $firstStore->getKey(),
                'category_id' => $firstCategoryId,
                'locale' => 'en',
                'title' => 'Shoes',
                'slug' => 'shoes',
            ],
            [
                'store_id' => $secondStore->getKey(),
                'category_id' => $secondCategoryId,
                'locale' => 'en',
                'title' => 'Shoes',
                'slug' => 'shoes',
            ],
        ]);

        self::assertSame(2, DB::table('category_translations')->where('slug', 'shoes')->count());
    }

    public function test_duplicate_localized_slug_in_one_store_is_rejected(): void
    {
        $store = Store::factory()->create();
        $firstCategoryId = $this->createCategory($store);
        $secondCategoryId = $this->createCategory($store);

        DB::table('category_translations')->insert([
            'store_id' => $store->getKey(),
            'category_id' => $firstCategoryId,
            'locale' => 'en',
            'title' => 'Shoes',
            'slug' => 'shoes',
        ]);

        $this->expectException(QueryException::class);

        DB::table('category_translations')->insert([
            'store_id' => $store->getKey(),
            'category_id' => $secondCategoryId,
            'locale' => 'en',
            'title' => 'More Shoes',
            'slug' => 'shoes',
        ]);
    }

    public function test_cross_store_product_category_assignment_is_rejected(): void
    {
        $categoryStore = Store::factory()->create();
        $productStore = Store::factory()->create();
        $categoryId = $this->createCategory($categoryStore);
        $productId = DB::table('products')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'store_id' => $productStore->getKey(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('product_categories')->insert([
            'store_id' => $productStore->getKey(),
            'category_id' => $categoryId,
            'product_id' => $productId,
        ]);
    }

    private function createCategory(Store $store): int
    {
        return DB::table('categories')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'store_id' => $store->getKey(),
        ]);
    }
}
