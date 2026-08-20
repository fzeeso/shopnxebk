<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Catalog\Models\PlatformTaxonomy;
use Modules\Catalog\Models\PlatformTaxonomyNode;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class ProductTypeDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_type_and_translation_schema_persist_requested_fields(): void
    {
        self::assertTrue(Schema::hasColumns('product_types', [
            'id',
            'public_id',
            'store_id',
            'code',
            'platform_taxonomy_node_id',
            'is_active',
            'sort_order',
            'created_at',
            'updated_at',
        ]));
        self::assertTrue(Schema::hasColumns('product_type_translations', [
            'id',
            'product_type_id',
            'store_id',
            'locale',
            'name',
            'slug',
            'description',
            'lock_it',
            'created_at',
            'updated_at',
        ]));

        $store = Store::factory()->create();
        $taxonomy = PlatformTaxonomy::query()->create([
            'name' => 'Global Commerce Taxonomy',
            'code' => 'global-commerce',
            'version' => 1,
            'status' => 'active',
            'is_default' => true,
        ]);
        $taxonomyNode = PlatformTaxonomyNode::query()->create([
            'taxonomy_id' => $taxonomy->getKey(),
            'name' => 'Running Shoes',
            'slug' => 'running-shoes',
            'code' => 'RUNNING-SHOES',
            'level' => 0,
            'path' => '/running-shoes',
        ]);
        $productTypeId = $this->createProductType($store, 'running-shoe', (int) $taxonomyNode->getKey());

        $translationId = DB::table('product_type_translations')->insertGetId([
            'product_type_id' => $productTypeId,
            'store_id' => $store->getKey(),
            'locale' => 'en',
            'name' => 'Running shoe',
            'slug' => 'running-shoe',
            'description' => 'Footwear designed for running.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('product_types', [
            'id' => $productTypeId,
            'store_id' => $store->getKey(),
            'code' => 'running-shoe',
            'platform_taxonomy_node_id' => $taxonomyNode->getKey(),
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('product_type_translations', [
            'id' => $translationId,
            'product_type_id' => $productTypeId,
            'store_id' => $store->getKey(),
            'locale' => 'en',
            'name' => 'Running shoe',
            'slug' => 'running-shoe',
            'lock_it' => false,
        ]);
    }

    public function test_product_type_translation_is_unique_by_product_type_and_locale(): void
    {
        $store = Store::factory()->create();
        $productTypeId = $this->createProductType($store, 'running-shoe');
        $this->createTranslation($store, $productTypeId, 'en', 'running-shoe');

        $this->expectException(QueryException::class);

        $this->createTranslation($store, $productTypeId, 'en', 'road-running-shoe');
    }

    public function test_product_type_translation_slug_is_unique_by_store_and_locale(): void
    {
        $store = Store::factory()->create();
        $firstProductTypeId = $this->createProductType($store, 'running-shoe');
        $secondProductTypeId = $this->createProductType($store, 'trail-shoe');
        $this->createTranslation($store, $firstProductTypeId, 'en', 'shoe');

        $this->expectException(QueryException::class);

        $this->createTranslation($store, $secondProductTypeId, 'en', 'shoe');
    }

    public function test_product_type_translation_rejects_a_cross_store_parent(): void
    {
        $owningStore = Store::factory()->create();
        $otherStore = Store::factory()->create();
        $productTypeId = $this->createProductType($owningStore, 'running-shoe');

        $this->expectException(QueryException::class);

        $this->createTranslation($otherStore, $productTypeId, 'en', 'running-shoe');
    }

    private function createProductType(
        Store $store,
        string $code,
        ?int $platformTaxonomyNodeId = null
    ): int {
        return DB::table('product_types')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'store_id' => $store->getKey(),
            'code' => $code,
            'platform_taxonomy_node_id' => $platformTaxonomyNodeId,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTranslation(
        Store $store,
        int $productTypeId,
        string $locale,
        string $slug
    ): void {
        DB::table('product_type_translations')->insert([
            'product_type_id' => $productTypeId,
            'store_id' => $store->getKey(),
            'locale' => $locale,
            'name' => Str::headline($slug),
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
