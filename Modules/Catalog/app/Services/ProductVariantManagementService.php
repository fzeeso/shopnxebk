<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductImage;
use Modules\Catalog\Models\ProductOptionValue;
use Modules\Catalog\Models\ProductVariant;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class ProductVariantManagementService
{
    private const ATTRIBUTE_NAMES = [
        'sku',
        'barcode',
        'price_amount_minor',
        'compare_at_price_amount_minor',
        'msrp_amount_minor',
        'cost_per_item_amount_minor',
        'currency_code',
        'inventory_qty',
        'inventory_policy',
        'weight_grams',
        'height',
        'width',
        'depth',
        'dimension_unit',
        'requires_shipping',
        'taxable',
        'call_for_price',
        'position',
    ];

    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private LocalizedTranslationWriter $translations,
    ) {}

    public function list(
        User $user,
        string $productPublicId,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return ProductVariant::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->with($this->relations())
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function show(User $user, string $productPublicId, string $variantPublicId): ProductVariant
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return $this->variant($store, $product, $variantPublicId)->load($this->relations());
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, string $productPublicId, array $input): ProductVariant
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $data = $this->validate($input, true);

        return DB::transaction(function () use ($store, $product, $data): ProductVariant {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $valueIds = $this->optionValueIds($store, $product, $data['option_value_ids'] ?? []);
            $this->ensureUniqueCombination($store, $product, $valueIds);
            $this->ensureSkuAvailable($store, $data['sku'] ?? null);
            $variant = ProductVariant::query()->create([
                'store_id' => $store->getKey(),
                'product_id' => $product->getKey(),
                ...$this->attributes($store, $product, $data),
            ]);
            $this->syncOptionValues($store, $product, $variant, $valueIds);
            if (isset($data['translations'])) {
                $this->syncTranslations($store, $variant, $data['translations']);
            }
            if (! $product->has_variants) {
                $product->forceFill(['has_variants' => true])->save();
            }

            return $variant->load($this->relations());
        });
    }

    /** @param array<string, mixed> $input */
    public function update(
        User $user,
        string $productPublicId,
        string $variantPublicId,
        array $input,
    ): ProductVariant {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $variant = $this->variant($store, $product, $variantPublicId);
        $data = $this->validate($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($store, $product, $variant, $data): ProductVariant {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $valueIds = null;
            if (array_key_exists('option_value_ids', $data)) {
                $valueIds = $this->optionValueIds($store, $product, $data['option_value_ids']);
                $this->ensureUniqueCombination($store, $product, $valueIds, (int) $variant->getKey());
            }
            if (array_key_exists('sku', $data)) {
                $this->ensureSkuAvailable($store, $data['sku'], (int) $variant->getKey());
            }
            $variant->fill($this->attributes($store, $product, $data))->save();
            if ($valueIds !== null) {
                $this->syncOptionValues($store, $product, $variant, $valueIds);
            }
            if (isset($data['translations'])) {
                $this->syncTranslations($store, $variant, $data['translations']);
            }

            return $variant->refresh()->load($this->relations());
        });
    }

    public function delete(User $user, string $productPublicId, string $variantPublicId): void
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $variant = $this->variant($store, $product, $variantPublicId);

        DB::transaction(function () use ($product, $variant): void {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $variant->delete();
            $product->forceFill(['has_variants' => $product->variants()->exists()])->save();
        });
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validate(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $nullableUnsigned = ['sometimes', 'nullable', 'integer', 'min:0'];
        $nullableDimension = ['sometimes', 'nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999.9999'];

        return Validator::make($input, [
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'price_amount_minor' => [$required, 'integer', 'min:0'],
            'compare_at_price_amount_minor' => $nullableUnsigned,
            'msrp_amount_minor' => $nullableUnsigned,
            'cost_per_item_amount_minor' => $nullableUnsigned,
            'currency_code' => [
                $required,
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
            'inventory_qty' => ['sometimes', 'integer'],
            'inventory_policy' => ['sometimes', 'in:deny,continue'],
            'weight_grams' => $nullableUnsigned,
            'height' => $nullableDimension,
            'width' => $nullableDimension,
            'depth' => $nullableDimension,
            'dimension_unit' => ['sometimes', 'string', 'max:10'],
            'requires_shipping' => ['sometimes', 'boolean'],
            'taxable' => ['sometimes', 'boolean'],
            'call_for_price' => ['sometimes', 'boolean'],
            'image_id' => ['sometimes', 'nullable', 'ulid'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'option_value_ids' => ['sometimes', 'array', 'list', 'max:100'],
            'option_value_ids.*' => ['required', 'ulid', 'distinct'],
            'translations' => ['sometimes', 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(Store $store, Product $product, array $data): array
    {
        $attributes = Arr::only($data, self::ATTRIBUTE_NAMES);
        if (array_key_exists('image_id', $data)) {
            $attributes['image_id'] = $data['image_id'] === null
                ? null
                : ProductImage::query()
                    ->where('store_id', $store->getKey())
                    ->where('product_id', $product->getKey())
                    ->where('public_id', $data['image_id'])
                    ->firstOrFail()
                    ->getKey();
        }

        return $attributes;
    }

    /** @param list<string> $publicIds @return list<int> */
    private function optionValueIds(Store $store, Product $product, array $publicIds): array
    {
        $options = $product->options()->get(['id']);
        $values = ProductOptionValue::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->whereIn('public_id', $publicIds)
            ->get(['id', 'option_id', 'public_id']);

        if ($values->count() !== count($publicIds)) {
            throw ValidationException::withMessages([
                'option_value_ids' => ['Every option value must belong to this Product.'],
            ]);
        }
        if ($options->isEmpty() && $values->isNotEmpty()) {
            throw ValidationException::withMessages([
                'option_value_ids' => ['A Product without options cannot receive option values.'],
            ]);
        }
        $selectedOptionIds = $values->pluck('option_id')->map(static fn (mixed $id): int => (int) $id);
        if ($selectedOptionIds->unique()->count() !== $selectedOptionIds->count()) {
            throw ValidationException::withMessages([
                'option_value_ids' => ['Select exactly one value from each Product option.'],
            ]);
        }
        $requiredOptionIds = $options->pluck('id')->map(static fn (mixed $id): int => (int) $id)->sort()->values();
        if ($selectedOptionIds->sort()->values()->all() !== $requiredOptionIds->all()) {
            throw ValidationException::withMessages([
                'option_value_ids' => ['Every variant must select exactly one value from every Product option.'],
            ]);
        }

        return $values->pluck('id')->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();
    }

    /** @param list<int> $valueIds */
    private function ensureUniqueCombination(
        Store $store,
        Product $product,
        array $valueIds,
        ?int $exceptVariantId = null,
    ): void {
        $variants = ProductVariant::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->when($exceptVariantId !== null, fn ($query) => $query->whereKeyNot($exceptVariantId))
            ->with('optionValues:id')
            ->get(['id']);
        foreach ($variants as $variant) {
            $existing = $variant->optionValues
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
            if ($existing === $valueIds) {
                throw ValidationException::withMessages([
                    'option_value_ids' => ['This variant option combination already exists.'],
                ]);
            }
        }
    }

    private function ensureSkuAvailable(Store $store, mixed $sku, ?int $exceptVariantId = null): void
    {
        if ($sku === null || trim((string) $sku) === '') {
            return;
        }
        $query = ProductVariant::query()
            ->where('store_id', $store->getKey())
            ->where('sku', (string) $sku);
        if ($exceptVariantId !== null) {
            $query->whereKeyNot($exceptVariantId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'sku' => ['The SKU is already used by another variant in this Store.'],
            ]);
        }
    }

    /** @param list<int> $valueIds */
    private function syncOptionValues(
        Store $store,
        Product $product,
        ProductVariant $variant,
        array $valueIds,
    ): void {
        DB::table('variant_option_values')->where('variant_id', $variant->getKey())->delete();
        $now = now();
        $rows = array_map(fn (int $valueId): array => [
            'store_id' => $store->getKey(),
            'product_id' => $product->getKey(),
            'variant_id' => $variant->getKey(),
            'option_value_id' => $valueId,
            'created_at' => $now,
        ], $valueIds);
        if ($rows !== []) {
            DB::table('variant_option_values')->insert($rows);
        }
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncTranslations(Store $store, ProductVariant $variant, array $translations): void
    {
        $this->translations->sync(
            $store,
            'product_variant_translations',
            'variant_id',
            (int) $variant->getKey(),
            $translations,
            ['title'],
        );
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage
            ? $this->access->ensureCanManageProducts($user, $store)
            : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function product(Store $store, string $publicId): Product
    {
        return Product::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function variant(Store $store, Product $product, string $publicId): ProductVariant
    {
        return ProductVariant::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'product',
            'preferredImage',
            'translations',
            'optionValues.translations',
            'optionValues.option.translations',
        ];
    }
}
