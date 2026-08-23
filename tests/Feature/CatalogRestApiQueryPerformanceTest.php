<?php

declare(strict_types=1);

namespace Tests\Feature;

use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductImage;
use Modules\Settings\Actions\EnsureLanguageCatalog;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class CatalogRestApiQueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_and_image_collections_do_not_add_queries_per_result(): void
    {
        app(EnsureLanguageCatalog::class)->ensure();
        $owner = User::factory()->create();
        $store = $this->provisionStore($owner);
        $headers = ['X-Store-ID' => (string) $store->public_id];
        $product = $this->createProduct($store, 'QUERY-BASE');
        ProductImage::query()->create([
            'store_id' => $store->getKey(),
            'product_id' => $product->getKey(),
            'url' => '/media/catalog/query-base.avif',
        ]);

        $singleProductQueries = $this->queryCount(fn () => $this->actingAs($owner, 'web')
            ->getJson('/api/v1/store/products', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data'));
        $singleImageQueries = $this->queryCount(fn () => $this->actingAs($owner, 'web')
            ->getJson("/api/v1/store/products/{$product->public_id}/images", $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data'));

        foreach (range(1, 10) as $index) {
            $this->createProduct($store, "QUERY-{$index}");
            ProductImage::query()->create([
                'store_id' => $store->getKey(),
                'product_id' => $product->getKey(),
                'url' => "/media/catalog/query-{$index}.avif",
                'position' => $index,
            ]);
        }

        $manyProductQueries = $this->queryCount(fn () => $this->actingAs($owner, 'web')
            ->getJson('/api/v1/store/products', $headers)
            ->assertOk()
            ->assertJsonCount(11, 'data'));
        $manyImageQueries = $this->queryCount(fn () => $this->actingAs($owner, 'web')
            ->getJson("/api/v1/store/products/{$product->public_id}/images", $headers)
            ->assertOk()
            ->assertJsonCount(11, 'data'));

        self::assertLessThanOrEqual(
            $singleProductQueries + 1,
            $manyProductQueries,
            "Product list query count grew from {$singleProductQueries} to {$manyProductQueries} with more rows.",
        );
        self::assertLessThanOrEqual(
            $singleImageQueries + 1,
            $manyImageQueries,
            "Product image list query count grew from {$singleImageQueries} to {$manyImageQueries} with more rows.",
        );
        self::assertLessThanOrEqual(30, $manyProductQueries, 'Product list exceeded its query budget.');
        self::assertLessThanOrEqual(25, $manyImageQueries, 'Product image list exceeded its query budget.');
    }

    private function createProduct(Store $store, string $sku): Product
    {
        return Product::query()->create([
            'store_id' => $store->getKey(),
            'sku' => $sku,
        ]);
    }

    private function queryCount(Closure $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $request();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function provisionStore(User $owner): Store
    {
        config(['stores.platform_domain' => 'stores.example.test']);

        return app(StoreProvisioner::class)->provision(
            $owner,
            'Catalog Query Store',
            'catalog-query-store',
            ['theme_template_key' => 'default'],
        );
    }
}
