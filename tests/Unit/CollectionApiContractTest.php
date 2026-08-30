<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Catalog\Http\Requests\CollectionNestedIndexRequest;
use Modules\Catalog\Http\Requests\CollectionWriteRequest;
use Modules\Catalog\Http\Requests\ReplaceCollectionProductsRequest;
use Modules\Catalog\Http\Requests\ReplaceCollectionRulesRequest;
use Modules\Catalog\Models\CollectionRule;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Services\CollectionManagementService;
use ReflectionMethod;
use Tests\TestCase;

final class CollectionApiContractTest extends TestCase
{
    public function test_collection_create_envelope_accepts_rules_and_manual_products(): void
    {
        $request = CollectionWriteRequest::create('/api/v1/store/collections', 'POST');
        $payload = [
            'collection_type' => 'rule_based',
            'rules_match_type' => 'all',
            'translations' => [[
                'locale' => 'en',
                'title' => 'Summer shoes',
                'slug' => 'summer-shoes',
            ]],
            'rules' => [[
                'field' => 'price',
                'operator' => 'less_than',
                'value' => '100.00',
            ]],
            'products' => [[
                'product_id' => (string) Str::ulid(),
                'is_pinned' => true,
            ]],
        ];

        $validated = Validator::make($payload, $request->rules())->validate();

        self::assertSame('rule_based', $validated['collection_type']);
        self::assertSame('less_than', $validated['rules'][0]['operator']);
        self::assertTrue($validated['products'][0]['is_pinned']);
    }

    public function test_nested_replacement_envelopes_allow_empty_complete_replacements(): void
    {
        $ruleRequest = ReplaceCollectionRulesRequest::create('/rules', 'PUT');
        $productRequest = ReplaceCollectionProductsRequest::create('/products', 'PUT');

        self::assertSame([], Validator::make(['rules' => []], $ruleRequest->rules())->validate()['rules']);
        self::assertSame([], Validator::make(['products' => []], $productRequest->rules())->validate()['products']);
    }

    public function test_product_type_rule_uses_the_normalized_relationship(): void
    {
        $query = Product::query()->withoutGlobalScopes();
        $rule = (new CollectionRule)->forceFill([
            'field' => 'product_type',
            'operator' => 'equals',
            'value' => 'shoe',
        ]);
        $method = new ReflectionMethod(CollectionManagementService::class, 'applyRule');
        $method->invoke(app(CollectionManagementService::class), $query, $rule);
        $sql = $query->toSql();

        self::assertStringContainsString('product_types', $sql);
        self::assertStringNotContainsString('products"."product_type"', $sql);
    }

    public function test_nested_collection_pages_are_bounded(): void
    {
        $request = CollectionNestedIndexRequest::create('/products', 'GET');

        self::assertTrue(Validator::make(['per_page' => 100], $request->rules())->passes());
        self::assertFalse(Validator::make(['per_page' => 101], $request->rules())->passes());
    }
}
