<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\RecordRequestPerformance;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Modules\Catalog\Http\Resources\ProductDetailResource;
use Modules\Catalog\Services\ProductDetailReferenceCache;
use Modules\Stores\Models\Store;
use Modules\Stores\StoreFinder\StoreLookupCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ScalabilityCacheTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $container = new Container;
        $container->instance('config', new ConfigRepository([
            'scalability' => [
                'store_lookup_cache' => [
                    'enabled' => true,
                    'ttl_seconds' => 30,
                    'prefix' => 'test:store-lookup',
                ],
                'product_detail_reference_cache' => [
                    'enabled' => true,
                    'ttl_seconds' => 300,
                    'prefix' => 'test:product-references',
                ],
                'request_performance' => [
                    'enabled' => true,
                    'slow_request_ms' => 60000,
                    'sample_rate' => 0.0,
                    'server_timing_header' => true,
                ],
            ],
        ]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_product_reference_cache_is_store_scoped_and_generation_invalidated(): void
    {
        $cache = new CacheRepository(new ArrayStore);
        $service = new ProductDetailReferenceCache($cache);
        $calls = 0;
        $resolver = function () use (&$calls): array {
            $calls++;

            return ['items' => [['id' => 'reference-'.$calls]]];
        };

        self::assertSame($service->remember(10, 100, $resolver), $service->remember(10, 100, $resolver));
        self::assertSame(1, $calls);

        $service->invalidateStore(10);
        self::assertSame('reference-2', $service->remember(10, 100, $resolver)['items'][0]['id']);
        self::assertSame('reference-3', $service->remember(11, 100, $resolver)['items'][0]['id']);
    }

    public function test_store_lookup_cache_rehydrates_a_store_without_querying_database(): void
    {
        $cache = new CacheRepository(new ArrayStore);
        $publicId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $cache->put('test:store-lookup:'.strtolower($publicId), [
            'id' => 42,
            'public_id' => $publicId,
            'name' => 'Cached Store',
            'status' => 'active',
        ], 30);

        $store = (new StoreLookupCache($cache))->findByPublicId($publicId);

        self::assertInstanceOf(Store::class, $store);
        self::assertSame(42, $store->getKey());
        self::assertSame('Cached Store', $store->name);
    }

    public function test_product_detail_resource_does_not_build_references_on_a_cache_hit(): void
    {
        $cache = new CacheRepository(new ArrayStore);
        $referenceCache = new ProductDetailReferenceCache($cache);
        Container::getInstance()->instance(ProductDetailReferenceCache::class, $referenceCache);
        $calls = 0;
        $source = function () use (&$calls): array {
            $calls++;

            return [
                'limit' => 100,
                'meta' => [],
                'brands' => [],
                'categories' => [],
                'product_types' => [],
                'platform_taxonomy_nodes' => [],
                'fulfillment_types' => [],
                'custom_fields' => [],
                'shared_options' => [],
                'modifiers' => [],
                'languages' => [],
                'currencies' => [],
                'store_defaults' => [],
            ];
        };
        $payload = [
            'product' => null,
            'sections' => [],
            'section_meta' => [],
            'writable_sections' => ['product'],
            'reference_data' => $source,
            'reference_cache' => ['store_id' => 10, 'limit' => 100],
        ];
        $request = Request::create('/api/v1/store/product-detail');

        (new ProductDetailResource($payload))->toArray($request);
        (new ProductDetailResource($payload))->toArray($request);

        self::assertSame(1, $calls);
    }

    public function test_performance_middleware_can_add_a_reversible_server_timing_header(): void
    {
        $response = (new RecordRequestPerformance)->handle(
            Request::create('/api/v1/store/product-detail'),
            static fn (): Response => new Response('ok'),
        );

        self::assertMatchesRegularExpression('/^app;dur=[0-9]+\.[0-9]{2}$/', (string) $response->headers->get('Server-Timing'));
    }
}
