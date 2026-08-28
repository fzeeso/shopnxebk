<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProductDetailApiContractTest extends TestCase
{
    public function test_product_detail_facade_exposes_one_read_and_one_save_contract(): void
    {
        $routes = $this->source('routes/product-api.php');

        foreach ([
            "Route::get('product-detail'",
            "Route::post('product-detail'",
            "Route::get('product-detail/{product}'",
            "Route::patch('product-detail/{product}'",
        ] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        self::assertStringContainsString("'store.bindings'", $routes);
    }

    public function test_read_model_composes_product_sections_and_selector_reference_data(): void
    {
        $reader = $this->source('Modules/Catalog/app/Services/ProductDetailReadService.php');
        $resource = $this->source('Modules/Catalog/app/Http/Resources/ProductDetailResource.php');

        foreach ([
            'images', 'media', 'custom_fields', 'options', 'variants', 'shared_options',
            'modifier_groups', 'modifiers',
        ] as $section) {
            self::assertStringContainsString("'{$section}'", $reader);
            self::assertStringContainsString("'{$section}'", $resource);
        }
        foreach ([
            'brands', 'categories', 'product_types', 'platform_taxonomy_nodes',
            'fulfillment_types', 'languages', 'currencies',
        ] as $reference) {
            self::assertStringContainsString("'{$reference}'", $reader);
        }
        self::assertStringContainsString("where('store_id', \$storeId)", $reader);
        self::assertStringContainsString("'truncated' => \$total > \$returned", $reader);
    }

    public function test_writer_is_atomic_partial_and_delegates_to_domain_services(): void
    {
        $writer = $this->source('Modules/Catalog/app/Services/ProductDetailWriteService.php');

        self::assertGreaterThanOrEqual(2, substr_count($writer, 'DB::transaction('));
        self::assertStringContainsString("\$sections = \$command['sections'] ?? []", $writer);
        self::assertStringContainsString('expected_updated_at', $writer);
        self::assertStringContainsString('ConflictHttpException', $writer);
        self::assertStringContainsString("str_starts_with(\$value, '@')", $writer);
        self::assertStringContainsString("'saved_sections'", $writer);
        self::assertStringNotContainsString('Http::', $writer);
        self::assertStringNotContainsString('curl_', $writer);

        foreach ([
            '$this->products->', '$this->images->', '$this->options->', '$this->variants->',
            '$this->customFields->', '$this->sharedOptions->', '$this->modifiers->', '$this->media->',
        ] as $delegate) {
            self::assertStringContainsString($delegate, $writer);
        }
    }

    public function test_existing_product_api_and_facade_share_the_same_input_mapper(): void
    {
        $controller = $this->source('Modules/Catalog/app/Http/Controllers/Api/V1/ProductController.php');
        $writer = $this->source('Modules/Catalog/app/Services/ProductDetailWriteService.php');
        $mapper = $this->source('Modules/Catalog/app/Support/ProductInputMapper.php');

        self::assertStringContainsString('ProductInputMapper::fromRest', $controller);
        self::assertStringContainsString('ProductInputMapper::fromRest', $writer);
        foreach (['brand_id', 'primary_category_id', 'seo_description', 'lock_it'] as $field) {
            self::assertStringContainsString("'{$field}'", $mapper);
        }
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }
}
