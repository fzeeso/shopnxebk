<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\ProductDetailReadRequest;
use App\Http\Requests\ProductDetailWriteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Contracts\ProductDetailSectionProvider;
use Modules\Catalog\Http\Resources\ProductDetailResource;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Services\ProductDetailSectionRegistry;
use Modules\Catalog\Support\ProductDetailReferenceMap;
use Modules\Catalog\Support\ProductDetailSectionPayload;
use Modules\Stores\Models\Store;
use Tests\TestCase;

final class ProductDetailSectionRegistryTest extends TestCase
{
    public function test_it_orders_providers_and_prefixes_their_validation_rules(): void
    {
        $registry = new ProductDetailSectionRegistry([
            $this->provider('subscriptions', 20),
            $this->provider('discounts', 10, [
                '' => ['sometimes', 'array:upsert,delete'],
                'upsert' => ['sometimes', 'array', 'list'],
                'upsert.*.ref' => ['sometimes', 'string'],
            ]),
        ]);

        self::assertSame(['discounts', 'subscriptions'], $registry->keys());
        self::assertSame([
            'sections.discounts' => ['sometimes', 'array:upsert,delete'],
            'sections.discounts.upsert' => ['sometimes', 'array', 'list'],
            'sections.discounts.upsert.*.ref' => ['sometimes', 'string'],
            'sections.subscriptions' => ['sometimes', 'array'],
        ], $registry->validationRules());
    }

    public function test_it_rejects_reserved_and_duplicate_section_keys(): void
    {
        foreach (['product', 'images'] as $reserved) {
            try {
                new ProductDetailSectionRegistry([$this->provider($reserved)]);
                self::fail('A reserved section key should fail registration.');
            } catch (\LogicException $exception) {
                self::assertStringContainsString('reserved', $exception->getMessage());
            }
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('registered more than once');
        new ProductDetailSectionRegistry([
            $this->provider('discounts'),
            $this->provider('discounts'),
        ]);
    }

    public function test_section_payload_reports_bounded_metadata(): void
    {
        $payload = new ProductDetailSectionPayload([['id' => 'one']], 4, 1);

        self::assertSame([
            'total' => 4,
            'returned' => 1,
            'limit' => 1,
            'truncated' => true,
        ], $payload->meta(1));
    }

    public function test_write_request_accepts_registered_sections_and_merges_their_rules(): void
    {
        $registry = new ProductDetailSectionRegistry([
            $this->provider('discounts', 10, [
                '' => ['sometimes', 'array:upsert'],
                'upsert' => ['sometimes', 'array', 'list'],
            ]),
        ]);
        $this->app->instance(ProductDetailSectionRegistry::class, $registry);
        $request = ProductDetailWriteRequest::create('/api/v1/store/product-detail', 'POST');
        $rules = $request->rules();

        self::assertStringContainsString('discounts', $rules['sections'][1]);
        self::assertSame(['sometimes', 'array:upsert'], $rules['sections.discounts']);
        self::assertSame(['sometimes', 'array', 'list'], $rules['sections.discounts.upsert']);
    }

    public function test_read_request_accepts_only_known_distinct_section_keys(): void
    {
        $this->app->instance(ProductDetailSectionRegistry::class, new ProductDetailSectionRegistry([
            $this->provider('discounts'),
        ]));
        $request = ProductDetailReadRequest::create('/api/v1/store/product-detail/example', 'GET', [
            'sections' => 'product,images,discounts',
        ]);
        $validator = Validator::make($request->all(), $request->rules());
        $request->setValidator($validator);
        $validator->validate();

        self::assertSame(['product', 'images', 'discounts'], $request->selectedSections());

        foreach (['images,images', 'images,unknown'] as $invalid) {
            $invalidRequest = ProductDetailReadRequest::create('/', 'GET', ['sections' => $invalid]);
            self::assertTrue(Validator::make($invalidRequest->all(), $invalidRequest->rules())->fails());
        }
    }

    public function test_resource_serializes_partial_sections_without_reducing_write_capabilities(): void
    {
        $resource = new ProductDetailResource([
            'product' => null,
            'sections' => [
                'images' => collect(),
                'discounts' => [['id' => '01DISCOUNT']],
            ],
            'section_meta' => [],
            'writable_sections' => ['product', 'images', 'options', 'discounts'],
        ]);

        $result = $resource->toArray(Request::create('/'));

        self::assertSame(['images', 'discounts'], array_keys($result['sections']));
        self::assertSame(
            ['product', 'images', 'options', 'discounts'],
            $result['capabilities']['writable_sections'],
        );
    }

    public function test_reference_map_shares_core_and_extension_references(): void
    {
        $references = new ProductDetailReferenceMap;
        $references->register('variants', 'small', '01VARIANT');
        $references->register('discount_rules', 'welcome', '01DISCOUNT');

        self::assertSame('01VARIANT', $references->resolve('variants', '@small'));
        self::assertSame('01DIRECT', $references->resolve('variants', '01DIRECT'));
        self::assertNull($references->nullable('variants', null));
        self::assertSame([
            'variants' => ['small' => '01VARIANT'],
            'discount_rules' => ['welcome' => '01DISCOUNT'],
        ], $references->all());
    }

    public function test_reference_map_rejects_unresolved_references(): void
    {
        $this->expectException(ValidationException::class);

        (new ProductDetailReferenceMap)->resolve('variants', '@missing');
    }

    /** @param array<string, list<mixed>> $rules */
    private function provider(string $key, int $priority = 100, array $rules = []): ProductDetailSectionProvider
    {
        return new class($key, $priority, $rules) implements ProductDetailSectionProvider
        {
            /** @param array<string, list<mixed>> $rules */
            public function __construct(
                private readonly string $sectionKey,
                private readonly int $sectionPriority,
                private readonly array $sectionRules,
            ) {}

            public function key(): string
            {
                return $this->sectionKey;
            }

            public function priority(): int
            {
                return $this->sectionPriority;
            }

            public function rules(): array
            {
                return $this->sectionRules;
            }

            public function bootstrap(User $user, Store $store, int $limit): ProductDetailSectionPayload
            {
                return ProductDetailSectionPayload::empty();
            }

            public function read(User $user, Store $store, Product $product, int $limit): ProductDetailSectionPayload
            {
                return ProductDetailSectionPayload::empty();
            }

            public function save(
                User $user,
                Store $store,
                Product $product,
                array $command,
                ProductDetailReferenceMap $references,
            ): void {}
        };
    }
}
