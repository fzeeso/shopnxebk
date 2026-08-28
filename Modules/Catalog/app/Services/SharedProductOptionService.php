<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductSharedOptionAssignment;
use Modules\Catalog\Models\SharedProductOption;
use Modules\Catalog\Models\SharedProductOptionValue;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class SharedProductOptionService
{
    public const TYPES = ['dropdown', 'radio_buttons', 'buttons', 'swatches'];

    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private LocalizedTranslationWriter $translations,
    ) {}

    /** @param array<string, mixed> $filters */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $data = Validator::make($filters, [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'type' => ['sometimes', 'nullable', 'in:'.implode(',', self::TYPES)],
        ])->validate();
        $query = SharedProductOption::query()
            ->where('store_id', $store->getKey())
            ->with($this->relations())
            ->withCount('assignments')
            ->orderBy('position')
            ->orderBy('id');
        if (($data['search'] ?? null) !== null) {
            $search = trim((string) $data['search']);
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('translations', fn ($translation) => $translation
                        ->where('display_name', 'ILIKE', "%{$search}%"))
                    ->orWhereHas('values.translations', fn ($translation) => $translation
                        ->where('display_label', 'ILIKE', "%{$search}%"));
            });
        }
        if (($data['type'] ?? null) !== null) {
            $query->where('type', $data['type']);
        }

        return $query->paginate(
            (int) ($data['per_page'] ?? 25),
            ['*'],
            'page',
            (int) ($data['page'] ?? 1),
        );
    }

    public function show(User $user, string $publicId): SharedProductOption
    {
        return $this->option($this->store($user, false), $publicId)
            ->load($this->relations())
            ->loadCount('assignments');
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, array $input): SharedProductOption
    {
        $store = $this->store($user, true);
        $this->ensureNameAvailable($store, (string) $input['name']);
        $this->ensureSingleDefault($input['values']);

        return DB::transaction(function () use ($input, $store): SharedProductOption {
            $option = SharedProductOption::query()->create([
                'store_id' => $store->getKey(),
                'name' => trim((string) $input['name']),
                'type' => $input['type'],
                'position' => $input['position'] ?? 0,
            ]);
            $this->syncOptionTranslations($store, $option, $input['translations']);
            $this->syncValues($store, $option, $input['values']);

            return $option->load($this->relations())->loadCount('assignments');
        });
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, string $publicId, array $input): SharedProductOption
    {
        $store = $this->store($user, true);
        $option = $this->option($store, $publicId);
        if ($input === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        if (isset($input['name'])) {
            $this->ensureNameAvailable($store, (string) $input['name'], (int) $option->getKey());
        }
        if (isset($input['values'])) {
            $this->ensureSingleDefault($input['values']);
        }

        return DB::transaction(function () use ($input, $option, $store): SharedProductOption {
            $option->fill(array_filter([
                'name' => isset($input['name']) ? trim((string) $input['name']) : null,
                'type' => $input['type'] ?? null,
                'position' => $input['position'] ?? null,
            ], static fn (mixed $value): bool => $value !== null))->save();
            if (isset($input['translations'])) {
                $this->syncOptionTranslations($store, $option, $input['translations']);
            }
            if (isset($input['values'])) {
                $this->syncValues($store, $option, $input['values']);
            }

            return $option->refresh()->load($this->relations())->loadCount('assignments');
        });
    }

    public function delete(User $user, string $publicId): void
    {
        $option = $this->option($this->store($user, true), $publicId);
        if ($option->assignments()->exists()) {
            throw ValidationException::withMessages([
                'option' => ['Remove this shared option from all Products before deleting it.'],
            ]);
        }
        $option->delete();
    }

    /** @return list<ProductSharedOptionAssignment> */
    public function assignments(User $user, string $productPublicId): array
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return ProductSharedOptionAssignment::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->with($this->assignmentRelations())
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @param array<string, mixed> $input */
    public function assign(User $user, string $productPublicId, array $input): ProductSharedOptionAssignment
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $data = Validator::make($input, [
            'option_id' => ['required', 'ulid'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ])->validate();
        $option = $this->option($store, (string) $data['option_id']);
        $assignment = ProductSharedOptionAssignment::query()->updateOrCreate(
            [
                'store_id' => $store->getKey(),
                'product_id' => $product->getKey(),
                'option_id' => $option->getKey(),
            ],
            ['position' => $data['position'] ?? 0],
        );

        return $assignment->load($this->assignmentRelations());
    }

    public function unassign(User $user, string $productPublicId, string $assignmentPublicId): void
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        ProductSharedOptionAssignment::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->where('public_id', $assignmentPublicId)
            ->firstOrFail()
            ->delete();
    }

    /** @param list<array<string, mixed>> $values */
    private function ensureSingleDefault(array $values): void
    {
        if (count(array_filter($values, static fn (array $value): bool => (bool) ($value['is_default'] ?? false))) > 1) {
            throw ValidationException::withMessages([
                'values' => ['Only one option value can be the default.'],
            ]);
        }
    }

    private function ensureNameAvailable(Store $store, string $name, ?int $exceptId = null): void
    {
        $query = SharedProductOption::query()
            ->where('store_id', $store->getKey())
            ->whereRaw('LOWER(name) = LOWER(?)', [trim($name)]);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => ['The option name has already been used in this Store.'],
            ]);
        }
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncOptionTranslations(Store $store, SharedProductOption $option, array $translations): void
    {
        $this->translations->sync(
            $store,
            'shared_product_option_translations',
            'option_id',
            (int) $option->getKey(),
            $translations,
            ['display_name'],
            ['display_name'],
        );
    }

    /** @param list<array<string, mixed>> $values */
    private function syncValues(Store $store, SharedProductOption $option, array $values): void
    {
        if (array_filter($values, static fn (array $value): bool => (bool) ($value['is_default'] ?? false)) !== []) {
            $option->values()->update(['is_default' => false]);
        }
        $keptIds = [];
        foreach ($values as $index => $valueData) {
            $value = isset($valueData['id'])
                ? SharedProductOptionValue::query()
                    ->where('store_id', $store->getKey())
                    ->where('option_id', $option->getKey())
                    ->where('public_id', $valueData['id'])
                    ->firstOrFail()
                : new SharedProductOptionValue([
                    'store_id' => $store->getKey(),
                    'option_id' => $option->getKey(),
                ]);
            $value->forceFill([
                'position' => $valueData['position'] ?? $index,
                'is_default' => (bool) ($valueData['is_default'] ?? false),
            ])->save();
            $this->translations->sync(
                $store,
                'shared_product_option_value_translations',
                'option_value_id',
                (int) $value->getKey(),
                $valueData['translations'],
                ['display_label'],
                ['display_label'],
            );
            $keptIds[] = (int) $value->getKey();
        }
        SharedProductOptionValue::query()
            ->where('store_id', $store->getKey())
            ->where('option_id', $option->getKey())
            ->whereNotIn('id', $keptIds)
            ->delete();
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

    private function option(Store $store, string $publicId): SharedProductOption
    {
        return SharedProductOption::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['translations', 'values.translations'];
    }

    /** @return list<string> */
    private function assignmentRelations(): array
    {
        return ['product', 'option.translations', 'option.values.translations'];
    }
}
