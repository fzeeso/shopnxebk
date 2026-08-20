<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Catalog\Models\Category;
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

final class CatalogGraphqlApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_product_graphql_lifecycle_is_store_scoped_and_translated(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'GraphQL Store', 'graphql-store');
        $this->enableLanguage($store, 'de');
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
        $categoryResponse = $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                mutation CreateCategory($input: CreateCategoryInput!) {
                  createCategory(input: $input) {
                    category { id isActive translation(locale: "en") { title slug imageUrl bannerUrl lockIt } }
                    translationRequest { id sourceLocale targetLocales }
                  }
                }
                GRAPHQL,
            'variables' => ['input' => [
                'sortOrder' => 10,
                'translations' => [[
                    'locale' => 'en',
                    'title' => 'Shoes',
                    'slug' => 'shoes',
                    'description' => 'All shoes',
                    'imageUrl' => '/catalog/localized/shoes.webp',
                    'bannerUrl' => '/catalog/localized/shoes-banner.webp',
                    'seoTitle' => 'Shop shoes',
                ]],
            ]],
        ], $headers)->assertOk()->assertJsonMissing(['errors']);
        $categoryId = (string) $categoryResponse->json('data.createCategory.category.id');
        $category = Category::query()->where('public_id', $categoryId)->firstOrFail();
        $this->assertSame(
            '/catalog/localized/shoes.webp',
            $categoryResponse->json('data.createCategory.category.translation.imageUrl'),
        );
        $this->assertSame(
            '/catalog/localized/shoes-banner.webp',
            $categoryResponse->json('data.createCategory.category.translation.bannerUrl'),
        );
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->getKey(),
            'locale' => 'en',
            'image_url' => '/catalog/localized/shoes.webp',
            'banner_url' => '/catalog/localized/shoes-banner.webp',
        ]);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->getKey(),
            'locale' => 'de',
            'title' => 'Shoes DE',
            'lock_it' => false,
        ]);

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
            'code' => 'FOOTWEAR-RUNNING',
            'level' => 0,
            'path' => '/running-shoes',
            'is_active' => true,
            'position' => 10,
        ]);
        $productType = ProductType::query()->create([
            'store_id' => $store->getKey(),
            'code' => 'running-shoe',
            'platform_taxonomy_node_id' => $taxonomyNode->getKey(),
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $productResponse = $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                mutation CreateProduct($input: CreateProductInput!) {
                  createProduct(input: $input) {
                    product {
                      id status primaryCategoryId platformTaxonomyNodeId productTypeId
                      categories { id translation(locale: "en") { title } }
                      translation(locale: "en") { title slug }
                    }
                    translationRequest { id sourceLocale targetLocales }
                  }
                }
                GRAPHQL,
            'variables' => ['input' => [
                'status' => 'active',
                'fulfillmentType' => 'physical',
                'platformTaxonomyNodeId' => (string) $taxonomyNode->public_id,
                'productTypeId' => (string) $productType->public_id,
                'categoryIds' => [$categoryId],
                'primaryCategoryId' => $categoryId,
                'translations' => [[
                    'locale' => 'en',
                    'title' => 'Trail Runner',
                    'slug' => 'trail-runner',
                    'description' => 'A durable shoe',
                ]],
            ]],
        ], $headers)->assertOk()->assertJsonMissing(['errors']);
        $productId = (string) $productResponse->json('data.createProduct.product.id');
        $product = Product::query()->where('public_id', $productId)->firstOrFail();
        $this->assertSame($categoryId, $productResponse->json('data.createProduct.product.primaryCategoryId'));
        $this->assertSame(
            (string) $taxonomyNode->public_id,
            $productResponse->json('data.createProduct.product.platformTaxonomyNodeId'),
        );
        $this->assertSame(
            (string) $productType->public_id,
            $productResponse->json('data.createProduct.product.productTypeId'),
        );
        $this->assertSame($taxonomyNode->getKey(), $product->platform_taxonomy_node_id);
        $this->assertSame($productType->getKey(), $product->product_type_id);
        $this->assertNotNull($product->published_at);
        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->getKey(),
            'locale' => 'de',
            'title' => 'Trail Runner DE',
            'lock_it' => false,
        ]);

        $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                query Products($filter: ProductFilterInput) {
                  products(filter: $filter, perPage: 10) {
                    data { id translation(locale: "de") { title } categories { id } }
                    paginatorInfo { total currentPage perPage }
                  }
                }
                GRAPHQL,
            'variables' => ['filter' => ['search' => 'Trail Runner DE', 'locale' => 'de', 'categoryId' => $categoryId]],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.products.paginatorInfo.total', 1)
            ->assertJsonPath('data.products.data.0.id', $productId)
            ->assertJsonPath('data.products.data.0.translation.title', 'Trail Runner DE');

        $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                mutation UpdateProduct($id: ID!, $input: UpdateProductInput!) {
                  updateProduct(id: $id, input: $input) {
                    product { id translation(locale: "de") { title lockIt } }
                  }
                }
                GRAPHQL,
            'variables' => [
                'id' => $productId,
                'input' => ['translations' => [[
                    'locale' => 'de',
                    'title' => 'Manuell bearbeitet',
                    'slug' => 'manuell-bearbeitet',
                    'lockIt' => true,
                ]]],
            ],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.updateProduct.product.translation.title', 'Manuell bearbeitet')
            ->assertJsonPath('data.updateProduct.product.translation.lockIt', true);

        $otherOwner = User::factory()->create();
        $otherStore = $this->provisionStore($otherOwner, 'Other Store', 'other-store');
        $this->actingAs($otherOwner, 'web')->postJson('/graphql', [
            'query' => 'query Category($id: ID!) { category(id: $id) { id } }',
            'variables' => ['id' => $categoryId],
        ], ['X-Store-ID' => (string) $otherStore->public_id])
            ->assertOk()
            ->assertJsonStructure(['errors']);

        Http::assertSentCount(2);
    }

    public function test_catalog_graphql_requires_authentication_and_valid_primary_category_assignment(): void
    {
        $this->postJson('/graphql', [
            'query' => '{ categories { paginatorInfo { total } } }',
        ])->assertOk()->assertJsonStructure(['errors']);

        $owner = User::factory()->create();
        $store = $this->provisionStore($owner, 'Validation Store', 'validation-store');
        $headers = ['X-Store-ID' => (string) $store->public_id];
        $this->actingAs($owner, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                mutation InvalidProduct($input: CreateProductInput!) {
                  createProduct(input: $input) { product { id } }
                }
                GRAPHQL,
            'variables' => ['input' => [
                'categoryIds' => [],
                'primaryCategoryId' => (string) Str::ulid(),
                'translations' => [['locale' => 'en', 'title' => 'Invalid', 'slug' => 'invalid']],
            ]],
        ], $headers)
            ->assertOk()
            ->assertJsonStructure(['errors']);

        $salesUser = User::factory()->create();
        StoreMembership::query()->create([
            'store_id' => $store->getKey(),
            'user_id' => $salesUser->getKey(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        app(ScopedRoleAssignmentService::class)->assignStoreRole($salesUser, $store, 'Sales');

        $this->actingAs($salesUser, 'web')->postJson('/graphql', [
            'query' => '{ categories { paginatorInfo { total } } }',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.categories.paginatorInfo.total', 0);

        $this->actingAs($salesUser, 'web')->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                mutation ForbiddenCategory($input: CreateCategoryInput!) {
                  createCategory(input: $input) { category { id } }
                }
                GRAPHQL,
            'variables' => ['input' => [
                'translations' => [['locale' => 'en', 'title' => 'Forbidden', 'slug' => 'forbidden']],
            ]],
        ], $headers)
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
}
