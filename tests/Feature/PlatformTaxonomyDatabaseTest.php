<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Models\CustomFieldDefinition;
use Modules\Catalog\Models\PlatformTaxonomy;
use Modules\Catalog\Models\PlatformTaxonomyCustomField;
use Modules\Catalog\Models\PlatformTaxonomyNode;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductType;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class PlatformTaxonomyDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_taxonomy_relations_classify_products_and_assign_custom_fields(): void
    {
        self::assertTrue(Schema::hasColumns('platform_taxonomies', [
            'id',
            'public_id',
            'name',
            'code',
            'version',
            'status',
            'is_default',
            'created_at',
            'updated_at',
        ]));
        self::assertTrue(Schema::hasColumns('platform_taxonomy_nodes', [
            'id',
            'public_id',
            'taxonomy_id',
            'parent_id',
            'name',
            'slug',
            'code',
            'level',
            'path',
            'description',
            'is_active',
            'position',
            'created_at',
            'updated_at',
        ]));
        self::assertTrue(Schema::hasColumns('platform_taxonomy_custom_fields', [
            'id',
            'taxonomy_node_id',
            'custom_field_definition_id',
            'is_required',
            'is_filterable',
            'is_searchable',
            'is_variant',
            'position',
            'created_at',
            'updated_at',
        ]));
        self::assertTrue(Schema::hasColumns('products', [
            'platform_taxonomy_node_id',
            'product_type_id',
        ]));
        self::assertFalse(Schema::hasColumn('products', 'product_type'));

        $store = Store::factory()->create();
        $taxonomy = PlatformTaxonomy::query()->create([
            'name' => 'Global Commerce Taxonomy',
            'code' => 'global-commerce',
            'version' => 1,
            'status' => 'active',
            'is_default' => true,
        ]);
        $root = PlatformTaxonomyNode::query()->create([
            'taxonomy_id' => $taxonomy->getKey(),
            'name' => 'Apparel',
            'slug' => 'apparel',
            'code' => 'APPAREL',
            'level' => 0,
            'path' => '/apparel',
        ]);
        $leaf = PlatformTaxonomyNode::query()->create([
            'taxonomy_id' => $taxonomy->getKey(),
            'parent_id' => $root->getKey(),
            'name' => 'Shoes',
            'slug' => 'shoes',
            'code' => 'APPAREL-SHOES',
            'level' => 1,
            'path' => '/apparel/shoes',
            'position' => 10,
        ]);
        $definition = CustomFieldDefinition::query()->create([
            'store_id' => $store->getKey(),
            'field_key' => 'material',
            'field_type' => 'text',
            'is_required' => false,
            'is_filterable' => true,
            'position' => 0,
        ]);
        $mapping = PlatformTaxonomyCustomField::query()->create([
            'taxonomy_node_id' => $leaf->getKey(),
            'custom_field_definition_id' => $definition->getKey(),
            'is_required' => true,
            'is_filterable' => true,
            'is_searchable' => true,
            'is_variant' => false,
            'position' => 5,
        ]);
        $productType = ProductType::query()->create([
            'store_id' => $store->getKey(),
            'code' => 'shoes',
            'platform_taxonomy_node_id' => $leaf->getKey(),
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $product = Product::query()->create([
            'store_id' => $store->getKey(),
            'platform_taxonomy_node_id' => $leaf->getKey(),
            'product_type_id' => $productType->getKey(),
        ]);

        self::assertTrue($leaf->taxonomy->is($taxonomy));
        self::assertTrue($leaf->parent->is($root));
        self::assertTrue($mapping->taxonomyNode->is($leaf));
        self::assertTrue($mapping->customFieldDefinition->is($definition));
        self::assertTrue($product->platformTaxonomyNode->is($leaf));
        self::assertTrue($product->productType->is($productType));
        self::assertSame((string) $leaf->public_id, $product->platformTaxonomyNodePublicId());
        self::assertSame((string) $productType->public_id, $product->productTypePublicId());
        self::assertSame($productType->getKey(), $product->product_type_id);

        $leaf->delete();

        self::assertNull($product->refresh()->platform_taxonomy_node_id);
        self::assertNull($productType->refresh()->platform_taxonomy_node_id);
        $this->assertDatabaseMissing('platform_taxonomy_custom_fields', ['id' => $mapping->getKey()]);
    }

    public function test_product_type_relation_rejects_a_product_from_another_store(): void
    {
        $owningStore = Store::factory()->create();
        $otherStore = Store::factory()->create();
        $productType = ProductType::query()->create([
            'store_id' => $owningStore->getKey(),
            'code' => 'running-shoe',
        ]);

        $this->expectException(QueryException::class);

        Product::query()->create([
            'store_id' => $otherStore->getKey(),
            'product_type_id' => $productType->getKey(),
        ]);
    }
}
