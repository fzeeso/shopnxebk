<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomObjectApiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    /** @return iterable<string, array{string}> */
    public static function tableProvider(): iterable
    {
        foreach ([
            'custom_object_types',
            'custom_object_type_translations',
            'custom_object_fields',
            'custom_object_field_translations',
            'custom_object_entries',
            'custom_object_entry_translations',
            'custom_object_values',
            'custom_object_value_translations',
            'custom_object_value_references',
            'custom_object_references',
        ] as $table) {
            yield $table => [$table];
        }
    }

    #[DataProvider('tableProvider')]
    public function test_migration_defines_each_custom_object_table(string $table): void
    {
        $migration = $this->source('Modules/Catalog/database/migrations/2026_08_31_020000_create_custom_object_tables.php');

        self::assertStringContainsString("Schema::create('{$table}'", $migration);
    }

    public function test_schema_uses_project_identity_localization_and_tenant_conventions(): void
    {
        $migration = $this->source('Modules/Catalog/database/migrations/2026_08_31_020000_create_custom_object_tables.php');

        self::assertStringContainsString("\$table->ulid('public_id')->unique()", $migration);
        self::assertStringContainsString("\$table->foreignId('store_id')", $migration);
        self::assertStringContainsString("\$table->string('locale', 35)", $migration);
        self::assertStringNotContainsString('store_language_id', $migration);
        self::assertStringContainsString('custom_object_values_entry_type_store_fk', $migration);
        self::assertStringContainsString('custom_object_references_entry_type_fk', $migration);
    }

    public function test_relational_reference_tables_are_used_instead_of_raw_object_ids(): void
    {
        $migration = $this->source('Modules/Catalog/database/migrations/2026_08_31_020000_create_custom_object_tables.php');
        $references = $this->source('Modules/Catalog/app/Services/CustomObjectReferenceService.php');
        $values = $this->source('Modules/Catalog/app/Services/CustomObjectValueService.php');

        self::assertStringContainsString("Schema::create('custom_object_references'", $migration);
        self::assertStringContainsString("Schema::create('custom_object_value_references'", $migration);
        self::assertStringContainsString('CustomObjectReference::query()->create', $references);
        self::assertStringContainsString("DB::table('custom_object_value_references')->insert", $values);
    }

    public function test_existing_custom_fields_are_extended_with_reference_types(): void
    {
        $migration = $this->source('Modules/Catalog/database/migrations/2026_08_31_020100_add_custom_object_references_to_custom_fields.php');
        $request = $this->source('app/Http/Requests/CustomFieldDefinitionWriteRequest.php');
        $service = $this->source('Modules/Catalog/app/Services/CustomFieldManagementService.php');
        $graphql = $this->source('Modules/Catalog/graphql/schema.graphql');

        self::assertStringContainsString('reference_object_type_id', $migration);
        self::assertStringContainsString('object_reference', $request);
        self::assertStringContainsString('multi_object_reference', $request);
        self::assertStringContainsString('Use the Custom Object reference endpoint', $service);
        self::assertStringContainsString('OBJECT_REFERENCE @enum(value: "object_reference")', $graphql);
        self::assertStringContainsString('referenceObjectTypeId: ID', $graphql);
    }

    public function test_store_api_exposes_management_options_and_reference_routes(): void
    {
        $routes = $this->source('routes/custom-object-api.php');

        foreach ([
            "Route::get('custom-object-types'",
            "Route::post('custom-object-types'",
            "Route::get('custom-object-types/{type}/entries/options'",
            "Route::put('custom-object-references/{definition}'",
            "Route::put('products/{product}/custom-object-references/{definition}'",
        ] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        self::assertStringContainsString("'auth:sanctum'", $routes);
        self::assertStringContainsString("'store.bindings'", $routes);
    }

    public function test_controllers_use_narrow_custom_object_services(): void
    {
        $contracts = [
            'Modules/Catalog/app/Http/Controllers/Api/V1/CustomObjectTypeController.php' => 'CustomObjectTypeService',
            'Modules/Catalog/app/Http/Controllers/Api/V1/CustomObjectFieldController.php' => 'CustomObjectFieldService',
            'Modules/Catalog/app/Http/Controllers/Api/V1/CustomObjectEntryController.php' => 'CustomObjectEntryService',
            'Modules/Catalog/app/Http/Controllers/Api/V1/CustomObjectReferenceController.php' => 'CustomObjectReferenceService',
        ];

        foreach ($contracts as $controller => $service) {
            self::assertStringContainsString($service, $this->source($controller));
        }
    }

    public function test_service_layer_exposes_lifecycle_translation_values_and_reference_operations(): void
    {
        $types = $this->source('Modules/Catalog/app/Services/CustomObjectTypeService.php');
        $fields = $this->source('Modules/Catalog/app/Services/CustomObjectFieldService.php');
        $entries = $this->source('Modules/Catalog/app/Services/CustomObjectEntryService.php');
        $references = $this->source('Modules/Catalog/app/Services/CustomObjectReferenceService.php');

        foreach (['createType', 'updateType', 'archiveType', 'translateType', 'deleteType'] as $method) {
            self::assertStringContainsString("function {$method}(", $types);
        }
        foreach (['createField', 'updateField', 'reorderFields', 'deleteField'] as $method) {
            self::assertStringContainsString("function {$method}(", $fields);
        }
        foreach (['createEntry', 'updateEntry', 'archiveEntry', 'translateEntry', 'saveValues', 'deleteEntry'] as $method) {
            self::assertStringContainsString("function {$method}(", $entries);
        }
        foreach (['list', 'replace', 'clear'] as $method) {
            self::assertStringContainsString("function {$method}(", $references);
        }
    }

    public function test_product_detail_contract_contains_custom_objects_section(): void
    {
        $registry = $this->source('Modules/Catalog/app/Services/ProductDetailSectionRegistry.php');
        $resource = $this->source('Modules/Catalog/app/Http/Resources/ProductDetailResource.php');
        $request = $this->source('app/Http/Requests/ProductDetailWriteRequest.php');

        self::assertStringContainsString("'custom_objects'", $registry);
        self::assertStringContainsString("'custom_objects' => CustomObjectReferenceResource::class", $resource);
        self::assertStringContainsString("'sections.custom_objects.replace'", $request);
    }

    private function source(string $path): string
    {
        $contents = file_get_contents($this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($contents);

        return $contents;
    }
}
