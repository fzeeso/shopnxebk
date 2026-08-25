<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CatalogRestApiConsistencyTest extends TestCase
{
    public function test_catalog_rest_routes_and_openapi_operations_stay_consistent(): void
    {
        $expected = [
            'GET /api/v1/platform/settings/fulfillment-types',
            'POST /api/v1/platform/settings/fulfillment-types',
            'GET /api/v1/platform/settings/fulfillment-types/{fulfillmentType}',
            'PATCH /api/v1/platform/settings/fulfillment-types/{fulfillmentType}',
            'GET /api/v1/store/fulfillment-types',
            'GET /api/v1/store/products',
            'POST /api/v1/store/products',
            'GET /api/v1/store/products/{product}',
            'PATCH /api/v1/store/products/{product}',
            'DELETE /api/v1/store/products/{product}',
            'GET /api/v1/store/products/{product}/images',
            'POST /api/v1/store/products/{product}/images',
            'GET /api/v1/store/products/{product}/images/{image}',
            'PATCH /api/v1/store/products/{product}/images/{image}',
            'DELETE /api/v1/store/products/{product}/images/{image}',
            'GET /api/v1/store/products/{product}/options',
            'POST /api/v1/store/products/{product}/options',
            'GET /api/v1/store/products/{product}/options/{option}',
            'PATCH /api/v1/store/products/{product}/options/{option}',
            'DELETE /api/v1/store/products/{product}/options/{option}',
            'GET /api/v1/store/products/{product}/options/{option}/values',
            'POST /api/v1/store/products/{product}/options/{option}/values',
            'GET /api/v1/store/products/{product}/options/{option}/values/{value}',
            'PATCH /api/v1/store/products/{product}/options/{option}/values/{value}',
            'DELETE /api/v1/store/products/{product}/options/{option}/values/{value}',
            'GET /api/v1/store/products/{product}/variants',
            'POST /api/v1/store/products/{product}/variants',
            'GET /api/v1/store/products/{product}/variants/{variant}',
            'PATCH /api/v1/store/products/{product}/variants/{variant}',
            'DELETE /api/v1/store/products/{product}/variants/{variant}',
            'POST /api/v1/store/products/{product}/media',
            'DELETE /api/v1/store/products/{product}/media/{media}',
            'PUT /api/v1/store/products/{product}/media/{media}/primary',
        ];
        sort($expected);

        $implemented = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! $route instanceof Route || ! $this->isCatalogRestRoute($route)) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $implemented[] = $method.' /'.$route->uri();
            }
        }
        sort($implemented);

        self::assertSame($expected, $implemented, 'Catalog REST route methods drifted from the supported contract.');
        self::assertSame($expected, $this->documentedOperations(), 'Catalog REST routes drifted from docs/openapi.yaml.');
    }

    private function isCatalogRestRoute(Route $route): bool
    {
        $name = (string) $route->getName();

        if (Str::startsWith($name, [
            'api.v1.store.products.modifier-groups.',
            'api.v1.store.products.modifiers.',
        ])) {
            return false;
        }

        return Str::startsWith($name, [
            'api.v1.platform.settings.fulfillment-types.',
            'api.v1.store.fulfillment-types.',
            'api.v1.store.products.',
        ]);
    }

    /** @return list<string> */
    private function documentedOperations(): array
    {
        $contents = file_get_contents(base_path('docs/openapi.yaml'));
        self::assertIsString($contents);
        $operations = [];
        $currentPath = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^  (\/api\/v1\/(?:platform\/settings\/fulfillment-types|store\/(?:fulfillment-types|products))[^:]*):$/', $line, $match) === 1) {
                $currentPath = $match[1];

                continue;
            }
            if (preg_match('/^  \//', $line) === 1) {
                $currentPath = null;

                continue;
            }
            if ($currentPath !== null && preg_match('/^    (get|post|put|patch|delete):$/', $line, $match) === 1) {
                $operations[] = strtoupper($match[1]).' '.$currentPath;
            }
        }
        sort($operations);

        return $operations;
    }
}
