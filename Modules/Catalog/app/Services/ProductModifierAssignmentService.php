<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Support\Translations\StoreTranslationLanguages;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\ModifierDefinition;
use Modules\Catalog\Models\ModifierValue;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductModifierAssignment;
use Modules\Catalog\Models\ProductModifierGroup;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class ProductModifierAssignmentService
{
    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private StoreTranslationLanguages $languages,
    ) {}

    /** @return list<ProductModifierAssignment> */
    public function list(User $user, string $productPublicId): array
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return ProductModifierAssignment::query()
            ->where('store_id', $store->getKey())->where('product_id', $product->getKey())
            ->with($this->relations())->orderBy('sort_order')->orderBy('id')->get()->all();
    }

    public function show(User $user, string $productPublicId, string $assignmentPublicId): ProductModifierAssignment
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return $this->assignment($store, $product, $assignmentPublicId)->load($this->relations());
    }

    /** @param array<string, mixed> $input */
    public function assign(User $user, string $productPublicId, array $input): ProductModifierAssignment
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $data = $this->validate($input, true);
        $modifier = $this->modifier($store, (string) $data['modifier_id']);
        $this->ensureSelectionOverrides($modifier, $data);

        $existing = ProductModifierAssignment::withTrashed()
            ->where('store_id', $store->getKey())->where('product_id', $product->getKey())->where('modifier_id', $modifier->getKey())->first();
        if ($existing !== null && ! $existing->trashed()) {
            throw ValidationException::withMessages(['modifier_id' => ['This modifier is already assigned to the product.']]);
        }

        return DB::transaction(function () use ($data, $existing, $store, $product, $modifier): ProductModifierAssignment {
            $assignment = $existing ?? new ProductModifierAssignment;
            if ($existing !== null) {
                $assignment->restore();
            }
            $assignment->fill([
                'store_id' => $store->getKey(), 'product_id' => $product->getKey(), 'modifier_id' => $modifier->getKey(),
                ...$this->attributes($store, $product, $data),
            ])->save();
            $this->syncChildren($store, $assignment, $data);

            return $assignment->load($this->relations());
        });
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, string $productPublicId, string $assignmentPublicId, array $input): ProductModifierAssignment
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $assignment = $this->assignment($store, $product, $assignmentPublicId);
        $data = $this->validate($input, false, $assignment);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        $this->ensureSelectionOverrides($assignment->modifier()->firstOrFail(), $data, $assignment);

        return DB::transaction(function () use ($data, $store, $product, $assignment): ProductModifierAssignment {
            $assignment->fill($this->attributes($store, $product, $data))->save();
            $this->syncChildren($store, $assignment, $data);

            return $assignment->refresh()->load($this->relations());
        });
    }

    public function remove(User $user, string $productPublicId, string $assignmentPublicId): void
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $this->assignment($store, $product, $assignmentPublicId)->delete();
    }

    /** @param list<array{id: string, sort_order: int}> $items */
    public function reorder(User $user, string $productPublicId, array $items): array
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $data = Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'list', 'min:1'],
            'items.*.id' => ['required', 'ulid', 'distinct'],
            'items.*.sort_order' => ['required', 'integer'],
        ])->validate()['items'];

        DB::transaction(function () use ($data, $store, $product): void {
            foreach ($data as $item) {
                $this->assignment($store, $product, (string) $item['id'])
                    ->forceFill(['sort_order' => $item['sort_order']])->save();
            }
        });

        return $this->list($user, $productPublicId);
    }

    /** @return list<ProductModifierGroup> */
    public function groups(User $user, string $productPublicId): array
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return ProductModifierGroup::query()->where('store_id', $store->getKey())->where('product_id', $product->getKey())
            ->with('translations')->orderBy('sort_order')->orderBy('id')->get()->all();
    }

    public function showGroup(User $user, string $productPublicId, string $groupPublicId): ProductModifierGroup
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return $this->group($store, $product, $groupPublicId)->load('translations');
    }

    /** @param array<string, mixed> $input */
    public function createGroup(User $user, string $productPublicId, array $input): ProductModifierGroup
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $data = $this->validateGroup($input, true);
        $this->ensureGroupCodeAvailable($store, $product, (string) $data['code']);

        return DB::transaction(function () use ($store, $product, $data): ProductModifierGroup {
            $group = ProductModifierGroup::query()->create([
                'store_id' => $store->getKey(), 'product_id' => $product->getKey(),
                ...Arr::only($data, ['code', 'sort_order', 'is_active', 'settings']),
            ]);
            $this->replaceTranslations($group->translations(), $store, $data['translations']);

            return $group->load('translations');
        });
    }

    /** @param array<string, mixed> $input */
    public function updateGroup(User $user, string $productPublicId, string $groupPublicId, array $input): ProductModifierGroup
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $group = $this->group($store, $product, $groupPublicId);
        $data = $this->validateGroup($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        if (isset($data['code'])) {
            $this->ensureGroupCodeAvailable($store, $product, (string) $data['code'], (int) $group->getKey());
        }

        return DB::transaction(function () use ($store, $group, $data): ProductModifierGroup {
            $group->fill(Arr::only($data, ['code', 'sort_order', 'is_active', 'settings']))->save();
            if (isset($data['translations'])) {
                $this->replaceTranslations($group->translations(), $store, $data['translations']);
            }

            return $group->refresh()->load('translations');
        });
    }

    public function deleteGroup(User $user, string $productPublicId, string $groupPublicId): void
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $this->group($store, $product, $groupPublicId)->delete();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validate(array $input, bool $creating, ?ProductModifierAssignment $existing = null): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $data = Validator::make($input, [
            'modifier_id' => $creating ? ['required', 'ulid'] : ['prohibited'],
            'group_id' => ['sometimes', 'nullable', 'ulid'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'is_required_override' => ['sometimes', 'nullable', 'boolean'],
            'min_selections_override' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_selections_override' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'settings_override' => ['sometimes', 'nullable', 'array'],
            'translations' => ['sometimes', 'array', 'list'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name_override' => ['nullable', 'string', 'max:255'],
            'translations.*.description_override' => ['nullable', 'string'],
            'translations.*.placeholder_override' => ['nullable', 'string', 'max:500'],
            'translations.*.help_text_override' => ['nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'value_assignments' => ['sometimes', 'array', 'list'],
            'value_assignments.*.value_id' => ['required', 'ulid', 'distinct'],
            'value_assignments.*.is_enabled' => ['sometimes', 'boolean'],
            'value_assignments.*.is_default_override' => ['sometimes', 'nullable', 'boolean'],
            'value_assignments.*.sort_order' => ['sometimes', 'nullable', 'integer'],
            'value_assignments.*.settings_override' => ['sometimes', 'nullable', 'array'],
            'price_overrides' => ['sometimes', 'array', 'list'],
            'price_overrides.*' => $this->priceRules(false),
            'value_price_overrides' => ['sometimes', 'array', 'list'],
            'value_price_overrides.*' => $this->priceRules(true),
        ])->validate();
        $min = array_key_exists('min_selections_override', $data) ? $data['min_selections_override'] : $existing?->min_selections_override;
        $max = array_key_exists('max_selections_override', $data) ? $data['max_selections_override'] : $existing?->max_selections_override;
        if ($min !== null && $max !== null && $min > $max) {
            throw ValidationException::withMessages(['min_selections_override' => ['Minimum selections cannot exceed maximum selections.']]);
        }

        return $data;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateGroup(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'code' => [$required, 'string', 'max:100', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(Store $store, Product $product, array $data): array
    {
        $attributes = Arr::only($data, ['sort_order', 'is_active', 'is_required_override', 'min_selections_override', 'max_selections_override', 'settings_override']);
        if (array_key_exists('group_id', $data)) {
            $attributes['modifier_group_id'] = $data['group_id'] === null ? null : $this->group($store, $product, (string) $data['group_id'])->getKey();
        }

        return $attributes;
    }

    /** @param array<string, mixed> $data */
    private function syncChildren(Store $store, ProductModifierAssignment $assignment, array $data): void
    {
        if (array_key_exists('translations', $data)) {
            $this->replaceTranslations($assignment->translations(), $store, $data['translations']);
        }
        if (array_key_exists('value_assignments', $data)) {
            $assignment->valueAssignments()->delete();
            foreach ($data['value_assignments'] as $row) {
                $value = $this->value($store, (int) $assignment->modifier_id, (string) $row['value_id']);
                $assignment->valueAssignments()->create([
                    'store_id' => $store->getKey(), 'modifier_id' => $assignment->modifier_id,
                    'modifier_value_id' => $value->getKey(), ...Arr::except($row, ['value_id']),
                ]);
            }
        }
        if (array_key_exists('price_overrides', $data)) {
            $assignment->priceOverrides()->delete();
            foreach ($data['price_overrides'] as $row) {
                $assignment->priceOverrides()->create(['store_id' => $store->getKey(), ...$row]);
            }
        }
        if (array_key_exists('value_price_overrides', $data)) {
            $assignment->valuePriceOverrides()->delete();
            foreach ($data['value_price_overrides'] as $row) {
                $value = $this->value($store, (int) $assignment->modifier_id, (string) $row['value_id']);
                $assignment->valuePriceOverrides()->create([
                    'store_id' => $store->getKey(), 'modifier_id' => $assignment->modifier_id,
                    'modifier_value_id' => $value->getKey(), ...Arr::except($row, ['value_id']),
                ]);
            }
        }
    }

    /** @param mixed $relation @param list<array<string, mixed>> $rows */
    private function replaceTranslations(mixed $relation, Store $store, array $rows): void
    {
        $locales = [];
        foreach ($this->translationRows($store, $rows) as $row) {
            $locales[] = $row['locale'];
            $relation->updateOrCreate(
                ['locale' => $row['locale']],
                ['store_id' => $store->getKey(), ...Arr::except($row, ['locale'])],
            );
        }
        $relation->whereNotIn('locale', $locales)->delete();
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function translationRows(Store $store, array $rows): array
    {
        $active = $this->languages->activeFor($store)->pluck('locale')->map(fn (mixed $locale): string => strtolower(str_replace('-', '_', trim((string) $locale))))->all();
        $active = $active === [] ? [strtolower(str_replace('-', '_', (string) $store->language_code))] : $active;
        $seen = [];
        foreach ($rows as &$row) {
            $row['locale'] = str_replace('-', '_', trim((string) $row['locale']));
            $key = strtolower($row['locale']);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['translations' => ['Each translation must use one unique locale.']]);
            }
            if (! in_array($key, $active, true)) {
                throw ValidationException::withMessages(['translations' => ["The locale [{$row['locale']}] is not active for this Store."]]);
            }
            $seen[$key] = true;
        }
        unset($row);

        return $rows;
    }

    /** @return array<int, mixed> */
    private function priceRules(bool $withValue): array
    {
        $keys = $withValue ? 'value_id,currency_code,adjustment_type,amount,channel_id,customer_group_id,starts_at,ends_at,is_active' : 'currency_code,adjustment_type,amount,channel_id,customer_group_id,starts_at,ends_at,is_active';

        return [
            "array:{$keys}",
            function (string $attribute, mixed $value, \Closure $fail) use ($withValue): void {
                $rules = [
                    'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
                    'adjustment_type' => ['required', 'in:fixed,percentage'],
                    'amount' => ['required', 'numeric', 'decimal:0,4'],
                    'channel_id' => ['prohibited'],
                    'customer_group_id' => ['prohibited'],
                    'starts_at' => ['nullable', 'date'],
                    'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                    'is_active' => ['sometimes', 'boolean'],
                ];
                if ($withValue) {
                    $rules['value_id'] = ['required', 'ulid'];
                }
                $validator = Validator::make((array) $value, $rules);
                if ($validator->fails()) {
                    $fail($validator->errors()->first());
                }
            },
        ];
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['modifier.translations', 'modifier.values.translations', 'modifier.values.priceAdjustments', 'modifier.validationRules.translations', 'modifier.priceAdjustments', 'group.translations', 'translations', 'valueAssignments.value.translations', 'priceOverrides', 'valuePriceOverrides.value'];
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage ? $this->access->ensureCanManageProducts($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function product(Store $store, string $publicId): Product
    {
        return Product::query()->where('store_id', $store->getKey())->where('public_id', $publicId)->firstOrFail();
    }

    private function modifier(Store $store, string $publicId): ModifierDefinition
    {
        return ModifierDefinition::query()->where('store_id', $store->getKey())->where('public_id', $publicId)->firstOrFail();
    }

    private function value(Store $store, int $modifierId, string $publicId): ModifierValue
    {
        return ModifierValue::query()->where('store_id', $store->getKey())->where('modifier_id', $modifierId)->where('public_id', $publicId)->firstOrFail();
    }

    private function assignment(Store $store, Product $product, string $publicId): ProductModifierAssignment
    {
        return ProductModifierAssignment::query()->where('store_id', $store->getKey())->where('product_id', $product->getKey())->where('public_id', $publicId)->firstOrFail();
    }

    private function group(Store $store, Product $product, string $publicId): ProductModifierGroup
    {
        return ProductModifierGroup::query()->where('store_id', $store->getKey())->where('product_id', $product->getKey())->where('public_id', $publicId)->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    private function ensureSelectionOverrides(ModifierDefinition $modifier, array $data, ?ProductModifierAssignment $existing = null): void
    {
        $min = array_key_exists('min_selections_override', $data) ? $data['min_selections_override'] : $existing?->min_selections_override;
        $max = array_key_exists('max_selections_override', $data) ? $data['max_selections_override'] : $existing?->max_selections_override;
        $effectiveMin = $min ?? $modifier->min_selections;
        $effectiveMax = $max ?? $modifier->max_selections;
        if ($effectiveMin !== null && $effectiveMax !== null && $effectiveMin > $effectiveMax) {
            throw ValidationException::withMessages(['min_selections_override' => ['The effective minimum cannot exceed the effective maximum.']]);
        }
        if (! $modifier->supports_multiple && ($effectiveMax ?? 1) > 1) {
            throw ValidationException::withMessages(['max_selections_override' => ['A single-choice modifier cannot allow multiple selections.']]);
        }
        if (isset($data['value_assignments']) && ! $modifier->supports_multiple && collect($data['value_assignments'])->where('is_default_override', true)->count() > 1) {
            throw ValidationException::withMessages(['value_assignments' => ['A single-choice modifier can have only one Product default value.']]);
        }
    }

    private function ensureGroupCodeAvailable(Store $store, Product $product, string $code, ?int $exceptId = null): void
    {
        $query = ProductModifierGroup::withTrashed()->where('store_id', $store->getKey())->where('product_id', $product->getKey())->where('code', $code);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => ['The modifier group code is already in use for this Product.']]);
        }
    }
}
