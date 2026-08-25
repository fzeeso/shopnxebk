<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\Media;
use App\Support\Translations\StoreTranslationLanguages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\ModifierDefinition;
use Modules\Catalog\Models\ModifierLibraryCategory;
use Modules\Catalog\Models\ModifierValue;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class ModifierLibraryService
{
    public const TYPES = ['select', 'radio', 'buttons', 'swatch', 'checkbox', 'checkbox_group', 'text', 'textarea', 'number', 'date', 'datetime', 'file', 'image_upload', 'toggle'];

    public const RULE_TYPES = ['min_length', 'max_length', 'min_number', 'max_number', 'regex', 'allowed_file_extensions', 'max_file_size', 'max_files', 'min_date', 'max_date'];

    private const VALUE_TYPES = ['select', 'radio', 'buttons', 'swatch', 'checkbox', 'checkbox_group', 'toggle'];

    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private StoreTranslationLanguages $languages,
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
            'category_id' => ['sometimes', 'nullable', 'ulid'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();
        $query = ModifierDefinition::query()
            ->where('store_id', $store->getKey())
            ->with($this->relations())
            ->orderBy('sort_order')->orderBy('id');
        if (($data['search'] ?? null) !== null) {
            $search = trim((string) $data['search']);
            $query->where(function ($query) use ($search): void {
                $query->where('code', 'ILIKE', "%{$search}%")
                    ->orWhereHas('translations', fn ($translation) => $translation->where('name', 'ILIKE', "%{$search}%"));
            });
        }
        if (($data['type'] ?? null) !== null) {
            $query->where('type', $data['type']);
        }
        if (array_key_exists('is_active', $data)) {
            $query->where('is_active', (bool) $data['is_active']);
        }
        if (($data['category_id'] ?? null) !== null) {
            $query->where('library_category_id', $this->category($store, (string) $data['category_id'])->getKey());
        }

        return $query->paginate((int) ($data['per_page'] ?? 25), ['*'], 'page', (int) ($data['page'] ?? 1));
    }

    public function show(User $user, string $publicId): ModifierDefinition
    {
        return $this->modifier($this->store($user, false), $publicId)->load($this->relations());
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, array $input): ModifierDefinition
    {
        $store = $this->store($user, true);
        $data = $this->validateModifier($input, true);
        $this->ensureModifierCodeAvailable($store, (string) $data['code']);

        return DB::transaction(function () use ($data, $store): ModifierDefinition {
            $modifier = ModifierDefinition::query()->create($this->modifierAttributes($store, $data));
            $this->syncModifierChildren($store, $modifier, $data, true);

            return $modifier->load($this->relations());
        });
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, string $publicId, array $input): ModifierDefinition
    {
        $store = $this->store($user, true);
        $modifier = $this->modifier($store, $publicId);
        $data = $this->validateModifier($input, false, $modifier);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        if (isset($data['code'])) {
            $this->ensureModifierCodeAvailable($store, (string) $data['code'], (int) $modifier->getKey());
        }

        return DB::transaction(function () use ($data, $store, $modifier): ModifierDefinition {
            $modifier->fill(Arr::except($this->modifierAttributes($store, $data), ['store_id']))->save();
            $this->syncModifierChildren($store, $modifier, $data, false);

            return $modifier->refresh()->load($this->relations());
        });
    }

    public function delete(User $user, string $publicId): void
    {
        $this->modifier($this->store($user, true), $publicId)->delete();
    }

    public function setActive(User $user, string $publicId, bool $active): ModifierDefinition
    {
        $modifier = $this->modifier($this->store($user, true), $publicId);
        $modifier->forceFill(['is_active' => $active])->save();

        return $modifier->load($this->relations());
    }

    /** @return list<ModifierValue> */
    public function values(User $user, string $modifierPublicId): array
    {
        $store = $this->store($user, false);
        $modifier = $this->modifier($store, $modifierPublicId);

        return ModifierValue::query()
            ->where('store_id', $store->getKey())
            ->where('modifier_id', $modifier->getKey())
            ->with($this->valueRelations())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function showValue(User $user, string $modifierPublicId, string $valuePublicId): ModifierValue
    {
        $store = $this->store($user, false);
        $modifier = $this->modifier($store, $modifierPublicId);

        return $this->value($store, $modifier, $valuePublicId)->load($this->valueRelations());
    }

    /** @param array<string, mixed> $input */
    public function createValue(User $user, string $modifierPublicId, array $input): ModifierValue
    {
        $store = $this->store($user, true);
        $modifier = $this->modifier($store, $modifierPublicId);
        $data = $this->validateValue($input, true, $modifier);
        $this->ensureValueCodeAvailable($store, $modifier, (string) $data['code']);
        $this->ensureSingleDefaultValue($modifier, $data);

        return DB::transaction(function () use ($data, $modifier, $store): ModifierValue {
            $value = new ModifierValue(['store_id' => $store->getKey(), 'modifier_id' => $modifier->getKey()]);
            $this->saveValue($store, $value, $data, true);

            return $value->load($this->valueRelations());
        });
    }

    /** @param array<string, mixed> $input */
    public function updateValue(User $user, string $modifierPublicId, string $valuePublicId, array $input): ModifierValue
    {
        $store = $this->store($user, true);
        $modifier = $this->modifier($store, $modifierPublicId);
        $value = $this->value($store, $modifier, $valuePublicId);
        $data = $this->validateValue($input, false, $modifier);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        if (isset($data['code'])) {
            $this->ensureValueCodeAvailable($store, $modifier, (string) $data['code'], (int) $value->getKey());
        }
        $this->ensureSingleDefaultValue($modifier, $data, $value);

        return DB::transaction(function () use ($data, $store, $value): ModifierValue {
            $this->saveValue($store, $value, $data, false);

            return $value->refresh()->load($this->valueRelations());
        });
    }

    public function deleteValue(User $user, string $modifierPublicId, string $valuePublicId): void
    {
        $store = $this->store($user, true);
        $modifier = $this->modifier($store, $modifierPublicId);
        $this->value($store, $modifier, $valuePublicId)->delete();
    }

    /** @return list<ModifierLibraryCategory> */
    public function categories(User $user): array
    {
        $store = $this->store($user, false);

        return ModifierLibraryCategory::query()->where('store_id', $store->getKey())
            ->with('translations')->orderBy('sort_order')->orderBy('id')->get()->all();
    }

    public function showCategory(User $user, string $publicId): ModifierLibraryCategory
    {
        return $this->category($this->store($user, false), $publicId)->load('translations');
    }

    /** @param array<string, mixed> $input */
    public function createCategory(User $user, array $input): ModifierLibraryCategory
    {
        $store = $this->store($user, true);
        $data = $this->validateCategory($input, true);
        $this->ensureCategoryCodeAvailable($store, (string) $data['code']);

        return DB::transaction(function () use ($data, $store): ModifierLibraryCategory {
            $category = ModifierLibraryCategory::query()->create([
                'store_id' => $store->getKey(), 'code' => $data['code'],
                'sort_order' => $data['sort_order'] ?? 0, 'is_active' => $data['is_active'] ?? true,
            ]);
            $this->replaceTranslations($category->translations(), $store, $data['translations']);

            return $category->load('translations');
        });
    }

    /** @param array<string, mixed> $input */
    public function updateCategory(User $user, string $publicId, array $input): ModifierLibraryCategory
    {
        $store = $this->store($user, true);
        $category = $this->category($store, $publicId);
        $data = $this->validateCategory($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        if (isset($data['code'])) {
            $this->ensureCategoryCodeAvailable($store, (string) $data['code'], (int) $category->getKey());
        }

        return DB::transaction(function () use ($category, $data, $store): ModifierLibraryCategory {
            $category->fill(Arr::only($data, ['code', 'sort_order', 'is_active']))->save();
            if (isset($data['translations'])) {
                $this->replaceTranslations($category->translations(), $store, $data['translations']);
            }

            return $category->refresh()->load('translations');
        });
    }

    public function deleteCategory(User $user, string $publicId): void
    {
        $this->category($this->store($user, true), $publicId)->delete();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateModifier(array $input, bool $creating, ?ModifierDefinition $existing = null): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $data = Validator::make($input, [
            'category_id' => ['sometimes', 'nullable', 'ulid'],
            'code' => [$required, 'string', 'max:100', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'type' => [$required, 'in:'.implode(',', self::TYPES)],
            'is_active' => ['sometimes', 'boolean'],
            'is_required_default' => ['sometimes', 'boolean'],
            'supports_multiple' => ['sometimes', 'boolean'],
            'min_selections' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_selections' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sort_order' => ['sometimes', 'integer'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.placeholder' => ['nullable', 'string', 'max:500'],
            'translations.*.help_text' => ['nullable', 'string'],
            'translations.*.required_message' => ['nullable', 'string', 'max:500'],
            'translations.*.validation_message' => ['nullable', 'string', 'max:500'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'values' => ['sometimes', 'array', 'list'],
            'values.*.id' => ['sometimes', 'ulid'],
            'values.*.code' => ['required', 'string', 'max:100', 'distinct', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'values.*.sort_order' => ['sometimes', 'integer'],
            'values.*.is_default' => ['sometimes', 'boolean'],
            'values.*.is_active' => ['sometimes', 'boolean'],
            'values.*.colour_value' => ['nullable', 'string', 'max:50'],
            'values.*.image_id' => ['nullable', 'ulid'],
            'values.*.icon' => ['nullable', 'string', 'max:255'],
            'values.*.settings' => ['nullable', 'array'],
            'values.*.translations' => ['required', 'array', 'list', 'min:1'],
            'values.*.translations.*.locale' => ['required', 'string', 'max:35'],
            'values.*.translations.*.name' => ['required', 'string', 'max:255'],
            'values.*.translations.*.description' => ['nullable', 'string'],
            'values.*.translations.*.lock_it' => ['sometimes', 'boolean'],
            'values.*.price_adjustments' => ['sometimes', 'array', 'list'],
            'values.*.price_adjustments.*' => $this->priceRules(),
            'validation_rules' => ['sometimes', 'array', 'list'],
            'validation_rules.*.rule_type' => ['required', 'in:'.implode(',', self::RULE_TYPES)],
            'validation_rules.*.rule_value' => ['nullable', 'array'],
            'validation_rules.*.sort_order' => ['sometimes', 'integer'],
            'validation_rules.*.is_active' => ['sometimes', 'boolean'],
            'validation_rules.*.translations' => ['sometimes', 'array', 'list'],
            'validation_rules.*.translations.*.locale' => ['required', 'string', 'max:35'],
            'validation_rules.*.translations.*.message' => ['required', 'string', 'max:500'],
            'validation_rules.*.translations.*.lock_it' => ['sometimes', 'boolean'],
            'price_adjustments' => ['sometimes', 'array', 'list'],
            'price_adjustments.*' => $this->priceRules(),
        ])->validate();
        $min = array_key_exists('min_selections', $data) ? $data['min_selections'] : $existing?->min_selections;
        $max = array_key_exists('max_selections', $data) ? $data['max_selections'] : $existing?->max_selections;
        if ($min !== null && $max !== null && $min > $max) {
            throw ValidationException::withMessages(['min_selections' => ['Minimum selections cannot exceed maximum selections.']]);
        }
        $type = (string) ($data['type'] ?? $existing?->type ?? '');
        $multiple = (bool) ($data['supports_multiple'] ?? $existing?->supports_multiple ?? false);
        if (! $multiple && ($max ?? 1) > 1) {
            throw ValidationException::withMessages(['max_selections' => ['A single-choice modifier cannot allow multiple selections.']]);
        }
        if ($type === 'checkbox_group' && ! $multiple) {
            throw ValidationException::withMessages(['supports_multiple' => ['Checkbox-group modifiers must support multiple selections.']]);
        }
        if (isset($data['values']) && ! in_array($type, self::VALUE_TYPES, true) && $data['values'] !== []) {
            throw ValidationException::withMessages(['values' => ['Free-form modifiers cannot define catalogue values.']]);
        }
        if (! isset($data['values']) && $existing !== null && ! in_array($type, self::VALUE_TYPES, true) && $existing->values()->exists()) {
            throw ValidationException::withMessages(['values' => ['Remove catalogue values when converting a modifier to a free-form type.']]);
        }
        if (isset($data['values']) && ! $multiple && collect($data['values'])->where('is_default', true)->count() > 1) {
            throw ValidationException::withMessages(['values' => ['A single-choice modifier can have only one default value.']]);
        }
        $this->ensureValidationRules($data['validation_rules'] ?? []);

        return $data;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateCategory(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'code' => [$required, 'string', 'max:100', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @return array<string, mixed> */
    private function modifierAttributes(Store $store, array $data): array
    {
        $attributes = ['store_id' => $store->getKey()];
        foreach (['code', 'type', 'is_active', 'is_required_default', 'supports_multiple', 'min_selections', 'max_selections', 'sort_order', 'settings'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }
        if (array_key_exists('category_id', $data)) {
            $attributes['library_category_id'] = $data['category_id'] === null ? null : $this->category($store, (string) $data['category_id'])->getKey();
        }

        return $attributes;
    }

    /** @param array<string, mixed> $data */
    private function syncModifierChildren(Store $store, ModifierDefinition $modifier, array $data, bool $creating): void
    {
        if ($creating || isset($data['translations'])) {
            $this->replaceTranslations($modifier->translations(), $store, $data['translations']);
        }
        if (array_key_exists('values', $data)) {
            $this->syncValues($store, $modifier, $data['values']);
        }
        if (array_key_exists('validation_rules', $data)) {
            $modifier->validationRules()->delete();
            foreach ($data['validation_rules'] as $ruleData) {
                $rule = $modifier->validationRules()->create([
                    'store_id' => $store->getKey(),
                    ...Arr::only($ruleData, ['rule_type', 'rule_value', 'sort_order', 'is_active']),
                ]);
                $this->replaceTranslations($rule->translations(), $store, $ruleData['translations'] ?? []);
            }
        }
        if (array_key_exists('price_adjustments', $data)) {
            $modifier->priceAdjustments()->delete();
            foreach ($data['price_adjustments'] as $price) {
                $modifier->priceAdjustments()->create(['store_id' => $store->getKey(), ...$price]);
            }
        }
    }

    /** @param list<array<string, mixed>> $values */
    private function syncValues(Store $store, ModifierDefinition $modifier, array $values): void
    {
        $kept = [];
        foreach ($values as $valueData) {
            $value = isset($valueData['id'])
                ? ModifierValue::query()->where('store_id', $store->getKey())->where('modifier_id', $modifier->getKey())->where('public_id', $valueData['id'])->firstOrFail()
                : new ModifierValue(['store_id' => $store->getKey(), 'modifier_id' => $modifier->getKey()]);
            $attributes = Arr::only($valueData, ['code', 'sort_order', 'is_default', 'is_active', 'colour_value', 'icon', 'settings']);
            if (array_key_exists('image_id', $valueData)) {
                $attributes['image_id'] = $valueData['image_id'] === null ? null : Media::query()
                    ->where('store_id', $store->getKey())->where('public_id', $valueData['image_id'])->value('id');
                if ($valueData['image_id'] !== null && $attributes['image_id'] === null) {
                    throw ValidationException::withMessages(['values' => ['A modifier value image does not belong to this Store.']]);
                }
            }
            $value->fill($attributes)->save();
            $this->replaceTranslations($value->translations(), $store, $valueData['translations']);
            if (array_key_exists('price_adjustments', $valueData)) {
                $value->priceAdjustments()->delete();
                foreach ($valueData['price_adjustments'] as $price) {
                    $value->priceAdjustments()->create(['store_id' => $store->getKey(), ...$price]);
                }
            }
            $kept[] = (int) $value->getKey();
        }
        $modifier->values()->whereNotIn('id', $kept)->delete();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateValue(array $input, bool $creating, ModifierDefinition $modifier): array
    {
        if (! in_array($modifier->type, self::VALUE_TYPES, true)) {
            throw ValidationException::withMessages(['modifier' => ['Free-form modifiers cannot define catalogue values.']]);
        }
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'code' => [$required, 'string', 'max:100', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'sort_order' => ['sometimes', 'integer'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'colour_value' => ['sometimes', 'nullable', 'string', 'max:50'],
            'image_id' => ['sometimes', 'nullable', 'ulid'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'price_adjustments' => ['sometimes', 'array', 'list'],
            'price_adjustments.*' => $this->priceRules(),
        ])->validate();
    }

    /** @param array<string, mixed> $data */
    private function saveValue(Store $store, ModifierValue $value, array $data, bool $creating): void
    {
        $attributes = Arr::only($data, ['code', 'sort_order', 'is_default', 'is_active', 'colour_value', 'icon', 'settings']);
        if (array_key_exists('image_id', $data)) {
            $attributes['image_id'] = $data['image_id'] === null ? null : Media::query()
                ->where('store_id', $store->getKey())->where('public_id', $data['image_id'])->value('id');
            if ($data['image_id'] !== null && $attributes['image_id'] === null) {
                throw ValidationException::withMessages(['image_id' => ['The modifier value image does not belong to this Store.']]);
            }
        }
        $value->fill($attributes)->save();
        if ($creating || array_key_exists('translations', $data)) {
            $this->replaceTranslations($value->translations(), $store, $data['translations']);
        }
        if (array_key_exists('price_adjustments', $data)) {
            $value->priceAdjustments()->delete();
            foreach ($data['price_adjustments'] as $price) {
                $value->priceAdjustments()->create(['store_id' => $store->getKey(), ...$price]);
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
    private function priceRules(): array
    {
        return [
            'array:currency_code,adjustment_type,amount,channel_id,customer_group_id,starts_at,ends_at,is_active',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $validator = Validator::make((array) $value, [
                    'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
                    'adjustment_type' => ['required', 'in:fixed,percentage'],
                    'amount' => ['required', 'numeric', 'decimal:0,4'],
                    'channel_id' => ['prohibited'],
                    'customer_group_id' => ['prohibited'],
                    'starts_at' => ['nullable', 'date'],
                    'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                    'is_active' => ['sometimes', 'boolean'],
                ]);
                if ($validator->fails()) {
                    $fail($validator->errors()->first());
                }
            },
        ];
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['category.translations', 'translations', 'values.translations', 'values.image', 'values.priceAdjustments', 'validationRules.translations', 'priceAdjustments'];
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage ? $this->access->ensureCanManageProducts($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function modifier(Store $store, string $publicId): ModifierDefinition
    {
        return ModifierDefinition::query()->where('store_id', $store->getKey())->where('public_id', $publicId)->firstOrFail();
    }

    private function category(Store $store, string $publicId): ModifierLibraryCategory
    {
        return ModifierLibraryCategory::query()->where('store_id', $store->getKey())->where('public_id', $publicId)->firstOrFail();
    }

    private function value(Store $store, ModifierDefinition $modifier, string $publicId): ModifierValue
    {
        return ModifierValue::query()
            ->where('store_id', $store->getKey())
            ->where('modifier_id', $modifier->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function ensureModifierCodeAvailable(Store $store, string $code, ?int $exceptId = null): void
    {
        $query = ModifierDefinition::withTrashed()->where('store_id', $store->getKey())->where('code', $code);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => ['The modifier code is already in use for this Store.']]);
        }
    }

    private function ensureCategoryCodeAvailable(Store $store, string $code, ?int $exceptId = null): void
    {
        $query = ModifierLibraryCategory::withTrashed()->where('store_id', $store->getKey())->where('code', $code);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => ['The modifier category code is already in use for this Store.']]);
        }
    }

    private function ensureValueCodeAvailable(Store $store, ModifierDefinition $modifier, string $code, ?int $exceptId = null): void
    {
        $query = ModifierValue::withTrashed()
            ->where('store_id', $store->getKey())
            ->where('modifier_id', $modifier->getKey())
            ->where('code', $code);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => ['The modifier value code is already in use for this modifier.']]);
        }
    }

    /** @param array<string, mixed> $data */
    private function ensureSingleDefaultValue(ModifierDefinition $modifier, array $data, ?ModifierValue $existing = null): void
    {
        if ($modifier->supports_multiple || ($data['is_default'] ?? false) !== true) {
            return;
        }
        $query = $modifier->values()->where('is_default', true);
        if ($existing !== null) {
            $query->whereKeyNot($existing->getKey());
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['is_default' => ['A single-choice modifier can have only one default value.']]);
        }
    }

    /** @return list<string> */
    private function valueRelations(): array
    {
        return ['translations', 'image', 'priceAdjustments'];
    }

    /** @param list<array<string, mixed>> $rules */
    private function ensureValidationRules(array $rules): void
    {
        foreach ($rules as $index => $rule) {
            if (($rule['is_active'] ?? true) === false) {
                continue;
            }
            $type = (string) $rule['rule_type'];
            $value = (array) ($rule['rule_value'] ?? []);
            $scalar = $value['value'] ?? ($value === [] ? null : reset($value));
            $valid = match ($type) {
                'min_length', 'max_length' => filter_var($scalar, FILTER_VALIDATE_INT) !== false && (int) $scalar >= 0,
                'min_number', 'max_number' => is_numeric($scalar),
                'regex' => is_string($scalar) && @preg_match($scalar, '') !== false,
                'allowed_file_extensions' => isset($value['extensions']) && is_array($value['extensions'])
                    && $value['extensions'] !== []
                    && collect($value['extensions'])->every(fn (mixed $extension): bool => is_string($extension) && preg_match('/^[a-z0-9]+$/i', $extension) === 1),
                'max_file_size' => filter_var($value['bytes'] ?? null, FILTER_VALIDATE_INT) !== false && (int) ($value['bytes'] ?? 0) > 0,
                'max_files' => filter_var($scalar, FILTER_VALIDATE_INT) !== false && (int) $scalar > 0,
                'min_date', 'max_date' => is_string($scalar) && strtotime($scalar) !== false,
                default => false,
            };
            if (! $valid) {
                throw ValidationException::withMessages([
                    "validation_rules.{$index}.rule_value" => ["The rule value is invalid for [{$type}]."],
                ]);
            }
        }
    }
}
