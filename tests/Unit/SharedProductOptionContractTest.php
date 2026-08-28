<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SharedProductOptionContractTest extends TestCase
{
    public function test_shared_option_schema_supports_the_library_form(): void
    {
        $source = $this->source('Modules/Catalog/database/migrations/2026_08_28_000100_create_shared_product_option_tables.php');

        foreach ([
            'shared_product_options',
            'shared_product_option_translations',
            'shared_product_option_values',
            'shared_product_option_value_translations',
            'product_shared_option_assignments',
        ] as $table) {
            self::assertStringContainsString("Schema::create('{$table}'", $source);
        }
        foreach (["string('name'", "string('type'", "string('display_name'", "string('display_label'", "boolean('is_default'"] as $field) {
            self::assertStringContainsString($field, $source);
        }
        self::assertStringContainsString('shared_product_option_values_one_default', $source);
        self::assertStringContainsString("'dropdown', 'radio_buttons', 'buttons', 'swatches'", $source);
        self::assertStringNotContainsString("Schema::table('products'", $source);
        self::assertStringNotContainsString("Schema::table('product_options'", $source);
    }

    public function test_store_admin_routes_expose_shared_option_crud_and_product_assignments(): void
    {
        $routes = $this->source('routes/shared-product-option-api.php');
        $provider = $this->source('app/Providers/AppServiceProvider.php');

        foreach ([
            "Route::get('options'",
            "Route::post('options'",
            "Route::patch('options/{option}'",
            "Route::delete('options/{option}'",
            "Route::get('products/{product}/shared-options'",
            "Route::post('products/{product}/shared-options'",
            "Route::delete('products/{product}/shared-options/{assignment}'",
        ] as $endpoint) {
            self::assertStringContainsString($endpoint, $routes);
        }
        self::assertStringContainsString("'store.bindings'", $routes);
        self::assertStringContainsString('routes/shared-product-option-api.php', $provider);
    }

    public function test_write_contract_keeps_internal_and_translated_fields_separate(): void
    {
        $request = $this->source('app/Http/Requests/SharedProductOptionWriteRequest.php');
        $resource = $this->source('Modules/Catalog/app/Http/Resources/SharedProductOptionResource.php');
        $valueResource = $this->source('Modules/Catalog/app/Http/Resources/SharedProductOptionValueResource.php');

        foreach (['name', 'type', 'translations.*.display_name', 'values.*.is_default', 'values.*.translations.*.display_label'] as $field) {
            self::assertStringContainsString("'{$field}'", $request);
        }
        self::assertStringContainsString("'products_count'", $resource);
        self::assertStringContainsString("'display_name'", $resource);
        self::assertStringContainsString("'display_label'", $valueResource);
        self::assertStringContainsString("'is_default'", $valueResource);
        self::assertStringNotContainsString("'store_id' =>", $resource);
        self::assertStringNotContainsString("'store_id' =>", $valueResource);
    }

    public function test_service_scopes_every_lookup_and_enforces_one_default(): void
    {
        $service = $this->source('Modules/Catalog/app/Services/SharedProductOptionService.php');

        self::assertGreaterThanOrEqual(8, substr_count($service, "where('store_id', \$store->getKey())"));
        self::assertStringContainsString('ensureCanManageProducts', $service);
        self::assertStringContainsString('ensureCanView', $service);
        self::assertStringContainsString('Only one option value can be the default.', $service);
        self::assertStringContainsString("'shared_product_option_translations'", $service);
        self::assertStringContainsString("'shared_product_option_value_translations'", $service);
        self::assertStringContainsString("whereRaw('LOWER(name) = LOWER(?)'", $service);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }
}
