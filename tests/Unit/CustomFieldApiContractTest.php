<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CustomFieldApiContractTest extends TestCase
{
    public function test_rest_routes_cover_definitions_options_and_product_variant_values(): void
    {
        $routes = $this->source('routes/custom-field-api.php');
        $provider = $this->source('app/Providers/AppServiceProvider.php');

        foreach ([
            "Route::get('custom-fields'",
            "Route::post('custom-fields'",
            "Route::patch('custom-fields/{definition}'",
            "Route::delete('custom-fields/{definition}'",
            "Route::post('custom-fields/{definition}/options'",
            "Route::patch('custom-fields/{definition}/options/{option}'",
            "Route::delete('custom-fields/{definition}/options/{option}'",
            "Route::get('products/{product}/custom-field-values'",
            "Route::put('products/{product}/custom-field-values/{definition}'",
            "Route::delete('products/{product}/custom-field-values/{definition}'",
            "Route::get('products/{product}/variants/{variant}/custom-field-values'",
            "Route::put('products/{product}/variants/{variant}/custom-field-values/{definition}'",
            "Route::delete('products/{product}/variants/{variant}/custom-field-values/{definition}'",
        ] as $endpoint) {
            self::assertStringContainsString($endpoint, $routes);
        }
        self::assertStringContainsString("'store.bindings'", $routes);
        self::assertStringContainsString('routes/custom-field-api.php', $provider);
    }

    public function test_graphql_exposes_complete_custom_field_lifecycle(): void
    {
        $schema = $this->source('Modules/Catalog/graphql/schema.graphql');

        foreach ([
            'customFields(',
            'customField(id:',
            'productCustomFieldValues(',
            'productCustomFieldValue(',
            'createCustomField(',
            'updateCustomField(',
            'deleteCustomField(',
            'createCustomFieldOption(',
            'updateCustomFieldOption(',
            'deleteCustomFieldOption(',
            'setProductCustomFieldValue(',
            'deleteProductCustomFieldValue(',
        ] as $operation) {
            self::assertStringContainsString($operation, $schema);
        }
        foreach (['TEXT', 'NUMBER', 'BOOLEAN', 'SELECT', 'MULTI_SELECT', 'DATE', 'URL'] as $type) {
            self::assertStringContainsString($type, $schema);
        }
    }

    public function test_service_enforces_store_scope_permissions_and_typed_shapes(): void
    {
        $service = $this->source('Modules/Catalog/app/Services/CustomFieldManagementService.php');

        self::assertGreaterThanOrEqual(8, substr_count($service, "where('store_id', \$store->getKey())"));
        self::assertStringContainsString('ensureCanView', $service);
        self::assertStringContainsString('ensureCanManageProducts', $service);
        self::assertStringContainsString('validateTypedValue', $service);
        self::assertStringContainsString("'text', 'url' => 'translations'", $service);
        self::assertStringContainsString("'multi_select' => 'option_ids'", $service);
        self::assertStringContainsString('Every option must belong to the selected custom-field definition.', $service);
        self::assertStringContainsString('This custom field does not apply to the Product type.', $service);
    }

    public function test_resources_never_expose_internal_store_or_foreign_keys(): void
    {
        foreach ([
            'Modules/Catalog/app/Http/Resources/CustomFieldDefinitionResource.php',
            'Modules/Catalog/app/Http/Resources/CustomFieldOptionResource.php',
            'Modules/Catalog/app/Http/Resources/ProductCustomFieldValueResource.php',
        ] as $path) {
            $resource = $this->source($path);
            self::assertStringNotContainsString("'store_id' =>", $resource);
            self::assertStringNotContainsString("'definition_id' => \$this->definition_id", $resource);
            self::assertStringNotContainsString("'product_id' => \$this->product_id", $resource);
        }
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }
}
