<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductOption;
use Modules\Catalog\Models\ProductOptionValue;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class ProductOptionManagementService
{
    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private LocalizedTranslationWriter $translations,
    ) {}

    /** @return list<ProductOption> */
    public function list(User $user, string $productPublicId): array
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return ProductOption::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->with($this->relations())
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function show(User $user, string $productPublicId, string $optionPublicId): ProductOption
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return $this->option($store, $product, $optionPublicId)->load($this->relations());
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, string $productPublicId, array $input): ProductOption
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $data = $this->validateOption($input, true);

        return DB::transaction(function () use ($store, $product, $data): ProductOption {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            if ($product->variants()->exists()) {
                throw ValidationException::withMessages([
                    'option' => ['Delete the existing variants before adding another option dimension.'],
                ]);
            }
            $option = ProductOption::query()->create([
                'store_id' => $store->getKey(),
                'product_id' => $product->getKey(),
                'position' => $data['position'] ?? 0,
            ]);
            $this->syncOptionTranslations($store, $option, $data['translations']);
            foreach ($data['values'] ?? [] as $valueData) {
                $value = ProductOptionValue::query()->create([
                    'store_id' => $store->getKey(),
                    'product_id' => $product->getKey(),
                    'option_id' => $option->getKey(),
                    'position' => $valueData['position'] ?? 0,
                ]);
                $this->syncValueTranslations($store, $value, $valueData['translations']);
            }

            return $option->load($this->relations());
        });
    }

    /** @param array<string, mixed> $input */
    public function update(
        User $user,
        string $productPublicId,
        string $optionPublicId,
        array $input,
    ): ProductOption {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $option = $this->option($store, $product, $optionPublicId);
        $data = $this->validateOption($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($store, $option, $data): ProductOption {
            if (array_key_exists('position', $data)) {
                $option->forceFill(['position' => $data['position']])->save();
            }
            if (isset($data['translations'])) {
                $this->syncOptionTranslations($store, $option, $data['translations']);
            }

            return $option->refresh()->load($this->relations());
        });
    }

    public function delete(User $user, string $productPublicId, string $optionPublicId): void
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $option = $this->option($store, $product, $optionPublicId);
        DB::transaction(function () use ($product, $option): void {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            if ($product->variants()->exists()) {
                throw ValidationException::withMessages([
                    'option' => ['Delete the product variants before deleting an option dimension.'],
                ]);
            }
            $option->delete();
        });
    }

    public function showValue(
        User $user,
        string $productPublicId,
        string $optionPublicId,
        string $valuePublicId,
    ): ProductOptionValue {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);
        $option = $this->option($store, $product, $optionPublicId);

        return $this->value($store, $product, $option, $valuePublicId)->load($this->valueRelations());
    }

    /** @param array<string, mixed> $input */
    public function createValue(
        User $user,
        string $productPublicId,
        string $optionPublicId,
        array $input,
    ): ProductOptionValue {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $option = $this->option($store, $product, $optionPublicId);
        $data = $this->validateValue($input, true);

        return DB::transaction(function () use ($store, $product, $option, $data): ProductOptionValue {
            $value = ProductOptionValue::query()->create([
                'store_id' => $store->getKey(),
                'product_id' => $product->getKey(),
                'option_id' => $option->getKey(),
                'position' => $data['position'] ?? 0,
            ]);
            $this->syncValueTranslations($store, $value, $data['translations']);

            return $value->load($this->valueRelations());
        });
    }

    /** @param array<string, mixed> $input */
    public function updateValue(
        User $user,
        string $productPublicId,
        string $optionPublicId,
        string $valuePublicId,
        array $input,
    ): ProductOptionValue {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $option = $this->option($store, $product, $optionPublicId);
        $value = $this->value($store, $product, $option, $valuePublicId);
        $data = $this->validateValue($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($store, $value, $data): ProductOptionValue {
            if (array_key_exists('position', $data)) {
                $value->forceFill(['position' => $data['position']])->save();
            }
            if (isset($data['translations'])) {
                $this->syncValueTranslations($store, $value, $data['translations']);
            }

            return $value->refresh()->load($this->valueRelations());
        });
    }

    public function deleteValue(
        User $user,
        string $productPublicId,
        string $optionPublicId,
        string $valuePublicId,
    ): void {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $option = $this->option($store, $product, $optionPublicId);
        $value = $this->value($store, $product, $option, $valuePublicId);
        DB::transaction(function () use ($product, $value): void {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            if ($value->variants()->exists()) {
                throw ValidationException::withMessages([
                    'value' => ['This option value is selected by a variant and cannot be deleted.'],
                ]);
            }
            $value->delete();
        });
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateOption(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name' => ['required', 'string', 'max:100'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'values' => $creating
                ? ['sometimes', 'array', 'list', 'max:100']
                : ['prohibited'],
            'values.*.position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'values.*.translations' => ['required', 'array', 'list', 'min:1'],
            'values.*.translations.*.locale' => ['required', 'string', 'max:35'],
            'values.*.translations.*.value' => ['required', 'string', 'max:100'],
            'values.*.translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateValue(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.value' => ['required', 'string', 'max:100'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncOptionTranslations(Store $store, ProductOption $option, array $translations): void
    {
        $this->translations->sync(
            $store,
            'product_option_translations',
            'option_id',
            (int) $option->getKey(),
            $translations,
            ['name'],
            ['name'],
        );
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncValueTranslations(Store $store, ProductOptionValue $value, array $translations): void
    {
        $this->translations->sync(
            $store,
            'product_option_value_translations',
            'option_value_id',
            (int) $value->getKey(),
            $translations,
            ['value'],
            ['value'],
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

    private function option(Store $store, Product $product, string $publicId): ProductOption
    {
        return ProductOption::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function value(
        Store $store,
        Product $product,
        ProductOption $option,
        string $publicId,
    ): ProductOptionValue {
        return ProductOptionValue::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->where('option_id', $option->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['product', 'translations', 'values.translations'];
    }

    /** @return list<string> */
    private function valueRelations(): array
    {
        return ['product', 'option.translations', 'translations'];
    }
}
