<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Catalog\Models\PlatformTaxonomy;
use Modules\Catalog\Models\PlatformTaxonomyNode;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductType;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Settings\Models\Language;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Tests\TestCase;

final class ProductTypeGraphqlApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_type_graphql_lifecycle_is_store_scoped_localized_and_filterable(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Product Type Store', 'product-type-store');
        $this->enableLanguage($store, 'de');
        $taxonomyNode = $this->taxonomyNode();
        config(['services.openai.api_key' => 'test-api-key']);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $input = json_decode((string) $request->data()['input'][1]['content'], true, 512, JSON_THROW_ON_ERROR);
            $translations = [];
            foreach ($input['target_locales'] as $locale) {
                $fields = ['locale' => $locale];
                foreach ($input['source'] as $field => $value) {
                    $fields[$field] = $value === null ? null : "{$value} DE";
                }
                $translations[] = $fields;
            }

            return Http::response([
                'output_text' => json_encode(['translations' => $translations], JSON_THROW_ON_ERROR),
            ]);
        });

        $headers = ['X-Store-ID' => (string) $store->public_id];
        $createResponse = $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                mutation CreateProductType($input: CreateProductTypeInput!) {
                  createProductType(input: $input) {
                    productType {
                      id code platformTaxonomyNodeId isActive sortOrder productsCount
                      translations { locale name slug description lockIt }
                      translation(locale: "de") { name slug }
                    }
                    translationRequest { id status sourceLocale targetLocales }
                  }
                }
                GRAPHQL,
            'variables' => ['input' => [
                'code' => 'running-shoe',
                'platformTaxonomyNodeId' => (string) $taxonomyNode->public_id,
                'isActive' => true,
                'sortOrder' => 10,
                'translations' => [[
                    'locale' => 'en',
                    'name' => 'Running shoe',
                    'slug' => 'Running Shoe',
                    'description' => 'Footwear designed for running.',
                ]],
            ]],
        ], $headers)->assertOk()->assertJsonMissing(['errors']);

        $productTypeId = (string) $createResponse->json('data.createProductType.productType.id');
        $productType = ProductType::query()->where('public_id', $productTypeId)->firstOrFail();
        $this->assertSame(
            (string) $taxonomyNode->public_id,
            $createResponse->json('data.createProductType.productType.platformTaxonomyNodeId'),
        );
        $this->assertSame(['de'], $createResponse->json('data.createProductType.translationRequest.targetLocales'));
        $this->assertDatabaseHas('product_type_translations', [
            'product_type_id' => $productType->getKey(),
            'store_id' => $store->getKey(),
            'locale' => 'en',
            'name' => 'Running shoe',
            'slug' => 'running-shoe',
            'lock_it' => false,
        ]);
        $this->assertDatabaseHas('product_type_translations', [
            'product_type_id' => $productType->getKey(),
            'locale' => 'de',
            'name' => 'Running shoe DE',
            'slug' => 'running-shoe-de',
            'lock_it' => false,
        ]);

        $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                query ProductTypes($filter: ProductTypeFilterInput!) {
                  productTypes(filter: $filter, sortBy: CODE, perPage: 10) {
                    data { id code translation(locale: "DE") { name slug } }
                    paginatorInfo { total currentPage perPage }
                  }
                }
                GRAPHQL,
            'variables' => ['filter' => [
                'search' => 'Running shoe DE',
                'locale' => 'de',
                'code' => 'running-shoe',
                'platformTaxonomyNodeId' => (string) $taxonomyNode->public_id,
                'isActive' => true,
            ]],
        ], $headers)
            ->assertOk()
            ->assertJsonMissing(['errors'])
            ->assertJsonPath('data.productTypes.paginatorInfo.total', 1)
            ->assertJsonPath('data.productTypes.data.0.id', $productTypeId)
            ->assertJsonPath('data.productTypes.data.0.translation.name', 'Running shoe DE');

        $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                query ProductType($id: ID!) {
                  productType(id: $id) {
                    id code productsCount translation(locale: "en-US") { name }
                  }
                }
                GRAPHQL,
            'variables' => ['id' => $productTypeId],
        ], $headers)
            ->assertOk()
            ->assertJsonMissing(['errors'])
            ->assertJsonPath('data.productType.code', 'running-shoe')
            ->assertJsonPath('data.productType.productsCount', 0)
            ->assertJsonPath('data.productType.translation', null);

        $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                mutation UpdateProductType($id: ID!, $input: UpdateProductTypeInput!) {
                  updateProductType(id: $id, input: $input) {
                    productType {
                      id code platformTaxonomyNodeId isActive sortOrder
                      translation(locale: "de") { name slug lockIt }
                    }
                    translationRequest { id }
                  }
                }
                GRAPHQL,
            'variables' => [
                'id' => $productTypeId,
                'input' => [
                    'code' => 'trail-running-shoe',
                    'platformTaxonomyNodeId' => null,
                    'isActive' => false,
                    'sortOrder' => 20,
                    'translations' => [[
                        'locale' => 'de',
                        'name' => 'Laufschuh',
                        'slug' => 'laufschuh',
                        'description' => 'Manuell gepflegte Beschreibung.',
                        'lockIt' => true,
                    ]],
                ],
            ],
        ], $headers)
            ->assertOk()
            ->assertJsonMissing(['errors'])
            ->assertJsonPath('data.updateProductType.productType.code', 'trail-running-shoe')
            ->assertJsonPath('data.updateProductType.productType.platformTaxonomyNodeId', null)
            ->assertJsonPath('data.updateProductType.productType.isActive', false)
            ->assertJsonPath('data.updateProductType.productType.sortOrder', 20)
            ->assertJsonPath('data.updateProductType.productType.translation.name', 'Laufschuh')
            ->assertJsonPath('data.updateProductType.productType.translation.lockIt', true)
            ->assertJsonPath('data.updateProductType.translationRequest', null);

        $product = Product::query()->create([
            'store_id' => $store->getKey(),
            'product_type_id' => $productType->getKey(),
        ]);
        $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                mutation DeleteProductType($id: ID!) {
                  deleteProductType(id: $id) { id deleted }
                }
                GRAPHQL,
            'variables' => ['id' => $productTypeId],
        ], $headers)
            ->assertOk()
            ->assertJsonMissing(['errors'])
            ->assertJsonPath('data.deleteProductType.id', $productTypeId)
            ->assertJsonPath('data.deleteProductType.deleted', true);

        $this->assertDatabaseMissing('product_types', ['id' => $productType->getKey()]);
        self::assertNull($product->refresh()->product_type_id);
        Http::assertSentCount(1);
    }

    public function test_product_type_graphql_requires_membership_and_manage_products_for_writes(): void
    {
        $this->postJson('/graphql', [
            'query' => '{ productTypes { paginatorInfo { total } } }',
        ])->assertOk()->assertJsonStructure(['errors']);

        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Authorization Store', 'product-type-authorization');
        $productType = ProductType::query()->create([
            'store_id' => $store->getKey(),
            'code' => 'existing',
        ]);
        $headers = ['X-Store-ID' => (string) $store->public_id];

        $salesUser = User::factory()->create();
        StoreMembership::query()->create([
            'store_id' => $store->getKey(),
            'user_id' => $salesUser->getKey(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        app(ScopedRoleAssignmentService::class)->assignStoreRole($salesUser, $store, 'Sales');

        $this->actingAs($salesUser, 'web')->postJson('/graphql', [
            'query' => '{ productTypes { paginatorInfo { total } } }',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.productTypes.paginatorInfo.total', 1);

        $this->actingAs($salesUser, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                mutation ForbiddenProductType($input: CreateProductTypeInput!) {
                  createProductType(input: $input) { productType { id } }
                }
                GRAPHQL,
            'variables' => ['input' => [
                'code' => 'forbidden',
                'translations' => [['locale' => 'en', 'name' => 'Forbidden', 'slug' => 'forbidden']],
            ]],
        ], $headers)
            ->assertOk()
            ->assertJsonStructure(['errors']);

        $otherOwner = User::factory()->create();
        $otherStore = $this->provisionStore($otherOwner, 'Other Store', 'product-type-other');
        $this->actingAs($otherOwner, 'web')->postJson('/graphql', [
            'query' => 'query ProductType($id: ID!) { productType(id: $id) { id } }',
            'variables' => ['id' => (string) $productType->public_id],
        ], ['X-Store-ID' => (string) $otherStore->public_id])
            ->assertOk()
            ->assertJsonStructure(['errors']);
    }

    private function provisionStore(User $owner, string $name, string $slug): Store
    {
        config(['stores.platform_domain' => 'stores.example.test']);

        return app(StoreProvisioner::class)->provision(
            $owner,
            $name,
            $slug,
            ['theme_template_key' => 'default'],
        );
    }

    private function enableLanguage(Store $store, string $locale): void
    {
        $language = Language::query()->where('locale', $locale)->firstOrFail();
        DB::table('store_languages')->updateOrInsert(
            ['store_id' => $store->getKey(), 'language_id' => $language->getKey()],
            ['is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function taxonomyNode(): PlatformTaxonomyNode
    {
        $taxonomy = PlatformTaxonomy::query()->create([
            'name' => 'Global Commerce Taxonomy',
            'code' => 'global-commerce',
            'version' => 1,
            'status' => 'active',
            'is_default' => true,
        ]);

        return PlatformTaxonomyNode::query()->create([
            'taxonomy_id' => $taxonomy->getKey(),
            'name' => 'Running Shoes',
            'slug' => 'running-shoes',
            'code' => 'FOOTWEAR-RUNNING',
            'level' => 0,
            'path' => '/running-shoes',
            'is_active' => true,
            'position' => 10,
        ]);
    }
}
