<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Modules\Catalog\Models\Product;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Http\Middleware\EnsureStoreOwnedBindings;
use Modules\Stores\Models\Store;
use Modules\Stores\Support\StoreRuntimeDatabaseGuard;
use Modules\Themes\Models\StoreTheme;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StoreAdminIsolationGuardTest extends TestCase
{
    public function test_store_http_requests_cannot_execute_schema_commands(): void
    {
        $request = Request::create('/api/v1/store/products/example', 'DELETE');

        $this->expectException(AccessDeniedHttpException::class);
        (new StoreRuntimeDatabaseGuard)->assertAllowed($request, '/* request */ DROP TABLE products');
    }

    public function test_store_http_requests_can_execute_normal_scoped_record_deletes(): void
    {
        $request = Request::create('/api/v1/store/products/example', 'DELETE');

        (new StoreRuntimeDatabaseGuard)->assertAllowed(
            $request,
            'delete from "products" where "store_id" = ? and "id" = ?',
        );

        self::addToAssertionCount(1);
    }

    public function test_cross_store_route_model_binding_is_hidden(): void
    {
        $store = $this->store(10);
        $foreignTheme = (new StoreTheme)->forceFill(['id' => 99, 'store_id' => 20]);
        $request = $this->requestWithBinding('storeTheme', $foreignTheme);

        $this->expectException(NotFoundHttpException::class);
        (new EnsureStoreOwnedBindings($this->context($store)))->handle(
            $request,
            static fn (): Response => new Response(status: 204),
        );
    }

    public function test_current_store_route_model_binding_is_allowed(): void
    {
        $store = $this->store(10);
        $theme = (new StoreTheme)->forceFill(['id' => 99, 'store_id' => 10]);
        $request = $this->requestWithBinding('storeTheme', $theme);

        $response = (new EnsureStoreOwnedBindings($this->context($store)))->handle(
            $request,
            static fn (): Response => new Response(status: 204),
        );

        self::assertSame(204, $response->getStatusCode());
    }

    public function test_store_scoped_models_reject_cross_store_mutation(): void
    {
        $product = (new Product)->forceFill(['id' => 99, 'store_id' => 20]);
        $guard = new ReflectionMethod(Product::class, 'ensureCurrentStoreOwns');

        $this->expectException(ModelNotFoundException::class);
        $guard->invoke(null, $product, $this->context($this->store(10)));
    }

    private function requestWithBinding(string $name, object $model): Request
    {
        $request = Request::create("/api/v1/store/themes/{{$name}}", 'DELETE');
        $route = new Route(['DELETE'], "api/v1/store/themes/{{$name}}", static fn (): Response => new Response);
        $route->bind($request);
        $route->setParameter($name, $model);
        $request->setRouteResolver(static fn (): Route => $route);

        return $request;
    }

    private function store(int $id): Store
    {
        return (new Store)->forceFill(['id' => $id, 'public_id' => str_pad((string) $id, 26, '0', STR_PAD_LEFT)]);
    }

    private function context(Store $store): StoreContext
    {
        return new class($store) implements StoreContext
        {
            public function __construct(private ?Store $store) {}

            public function set(Store $store): void
            {
                $this->store = $store;
            }

            public function current(): ?Store
            {
                return $this->store;
            }

            public function id(): ?int
            {
                return $this->store?->getKey();
            }

            public function require(): Store
            {
                return $this->store ?? throw new \RuntimeException('Store required.');
            }

            public function clear(): void
            {
                $this->store = null;
            }
        };
    }
}
