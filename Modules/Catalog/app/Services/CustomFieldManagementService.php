<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\CustomFieldDefinition;
use Modules\Catalog\Models\CustomFieldOption;
use Modules\Catalog\Models\CustomObjectType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCustomFieldValue;
use Modules\Catalog\Models\ProductVariant;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class CustomFieldManagementService
{
    public const TYPES = [
        'text', 'number', 'boolean', 'select', 'multi_select', 'date', 'url',
        'object_reference', 'multi_object_reference',
    ];

    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private LocalizedTranslationWriter $translations,
    ) {}

    /** @param array<string, mixed> $arguments */
    public function listDefinitions(User $user, array $arguments): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $data = Validator::make($arguments, [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'filter.productType' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter.fieldKey' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter.fieldType' => ['sometimes', 'nullable', 'in:'.implode(',', self::TYPES)],
            'filter.isRequired' => ['sometimes', 'boolean'],
            'filter.isFilterable' => ['sometimes', 'boolean'],
            'sortBy' => ['sometimes', 'in:position,fieldKey,createdAt,updatedAt'],
            'sortDirection' => ['sometimes', 'in:ASC,DESC,asc,desc'],
        ])->validate();
        $filter = $data['filter'] ?? [];
        $query = CustomFieldDefinition::query()
            ->where('store_id', $store->getKey())
            ->with($this->definitionRelations())
            ->withCount('values');

        if (($filter['search'] ?? null) !== null && trim((string) $filter['search']) !== '') {
            $search = trim((string) $filter['search']);
            $query->where(function ($query) use ($search): void {
                $query->where('field_key', 'ILIKE', "%{$search}%")
                    ->orWhereHas('translations', fn ($translation) => $translation
                        ->where('label', 'ILIKE', "%{$search}%"));
            });
        }
        if (array_key_exists('productType', $filter) && $filter['productType'] !== null) {
            $query->where(function ($query) use ($filter): void {
                $query->whereNull('product_type')->orWhere('product_type', $filter['productType']);
            });
        }
        foreach ([
            'fieldKey' => 'field_key',
            'fieldType' => 'field_type',
            'isRequired' => 'is_required',
            'isFilterable' => 'is_filterable',
        ] as $input => $column) {
            if (array_key_exists($input, $filter) && $filter[$input] !== null) {
                $query->where($column, $filter[$input]);
            }
        }

        $sortColumn = match ($data['sortBy'] ?? 'position') {
            'fieldKey' => 'field_key',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
            default => 'position',
        };
        $query->orderBy($sortColumn, strtolower((string) ($data['sortDirection'] ?? 'ASC')))
            ->orderBy('id');

        return $query->paginate(
            (int) ($data['perPage'] ?? 25),
            ['*'],
            'page',
            (int) ($data['page'] ?? 1),
        );
    }

    public function showDefinition(User $user, string $publicId): CustomFieldDefinition
    {
        $store = $this->store($user, false);

        return $this->definition($store, $publicId)
            ->load($this->definitionRelations())
            ->loadCount('values');
    }

    /** @param array<string, mixed> $input */
    public function createDefinition(User $user, array $input): CustomFieldDefinition
    {
        $store = $this->store($user, true);
        $data = $this->validateDefinition($this->normalizeDefinitionInput($input), true);
        $this->ensureReferenceConfiguration($data, (string) $data['field_type']);
        $this->ensureFieldKeyAvailable($store, (string) $data['field_key']);
        $this->ensureOptionsAllowed((string) $data['field_type'], $data['options'] ?? []);

        return DB::transaction(function () use ($store, $data): CustomFieldDefinition {
            $definition = CustomFieldDefinition::query()->create([
                'store_id' => $store->getKey(),
                'product_type' => $data['product_type'] ?? null,
                'field_key' => trim((string) $data['field_key']),
                'field_type' => $data['field_type'],
                'reference_object_type_id' => isset($data['reference_object_type_id'])
                    ? $this->referenceObjectType($store, (string) $data['reference_object_type_id'])->getKey()
                    : null,
                'is_required' => $data['is_required'] ?? false,
                'is_filterable' => $data['is_filterable'] ?? false,
                'position' => $data['position'] ?? 0,
            ]);
            $this->syncDefinitionTranslations($store, $definition, $data['translations']);
            foreach ($data['options'] ?? [] as $optionData) {
                $this->createOptionRow($store, $definition, $optionData);
            }

            return $definition->load($this->definitionRelations())->loadCount('values');
        });
    }

    /** @param array<string, mixed> $input */
    public function updateDefinition(
        User $user,
        string $publicId,
        array $input,
    ): CustomFieldDefinition {
        $store = $this->store($user, true);
        $definition = $this->definition($store, $publicId);
        $data = $this->validateDefinition($this->normalizeDefinitionInput($input), false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        if (isset($data['field_key'])) {
            $this->ensureFieldKeyAvailable($store, (string) $data['field_key'], (int) $definition->getKey());
        }
        $this->ensureReferenceConfiguration($data, (string) ($data['field_type'] ?? $definition->field_type), $definition);
        if (isset($data['field_type']) && $data['field_type'] !== $definition->field_type) {
            if ($definition->values()->exists() || $definition->objectReferences()->exists()) {
                throw ValidationException::withMessages([
                    'field_type' => ['A field type cannot change after Product, Variant, or Custom Object references exist.'],
                ]);
            }
            if (! in_array($data['field_type'], ['select', 'multi_select'], true) && $definition->options()->exists()) {
                throw ValidationException::withMessages([
                    'field_type' => ['Delete the field options before changing to a non-select type.'],
                ]);
            }
        }
        if (isset($data['reference_object_type_id'])) {
            $referenceType = $this->referenceObjectType($store, (string) $data['reference_object_type_id']);
            if ((int) $referenceType->getKey() !== (int) $definition->reference_object_type_id
                && $definition->objectReferences()->exists()) {
                throw ValidationException::withMessages([
                    'reference_object_type_id' => ['Clear active Custom Object references before changing the reference type.'],
                ]);
            }
        }

        return DB::transaction(function () use ($store, $definition, $data): CustomFieldDefinition {
            if (isset($data['field_key'])) {
                $data['field_key'] = trim((string) $data['field_key']);
            }
            $attributes = array_intersect_key($data, array_flip([
                'product_type',
                'field_key',
                'field_type',
                'is_required',
                'is_filterable',
                'position',
            ]));
            if (isset($data['reference_object_type_id'])) {
                $attributes['reference_object_type_id'] = $this->referenceObjectType(
                    $store,
                    (string) $data['reference_object_type_id'],
                )->getKey();
            } elseif (isset($data['field_type']) && ! in_array(
                $data['field_type'],
                ['object_reference', 'multi_object_reference'],
                true,
            )) {
                $attributes['reference_object_type_id'] = null;
            }
            $definition->fill($attributes)->save();
            if (isset($data['translations'])) {
                $this->syncDefinitionTranslations($store, $definition, $data['translations']);
            }

            return $definition->refresh()->load($this->definitionRelations())->loadCount('values');
        });
    }

    public function deleteDefinition(User $user, string $publicId): void
    {
        $store = $this->store($user, true);
        $definition = $this->definition($store, $publicId);
        if ($definition->objectReferences()->exists()) {
            throw ValidationException::withMessages([
                'definition' => ['Clear active Custom Object references before deleting this Custom Field.'],
            ]);
        }
        $definition->delete();
    }

    /** @return list<CustomFieldOption> */
    public function listOptions(User $user, string $definitionPublicId): array
    {
        $definition = $this->showDefinition($user, $definitionPublicId);

        return $definition->options->all();
    }

    public function showOption(
        User $user,
        string $definitionPublicId,
        string $optionPublicId,
    ): CustomFieldOption {
        $store = $this->store($user, false);
        $definition = $this->definition($store, $definitionPublicId);

        return $this->option($store, $definition, $optionPublicId)->load($this->optionRelations());
    }

    /** @param array<string, mixed> $input */
    public function createOption(
        User $user,
        string $definitionPublicId,
        array $input,
    ): CustomFieldOption {
        $store = $this->store($user, true);
        $definition = $this->definition($store, $definitionPublicId);
        $this->ensureSelectDefinition($definition);
        $data = $this->validateOption($this->normalizeOptionInput($input), true);

        return DB::transaction(fn (): CustomFieldOption => $this->createOptionRow($store, $definition, $data));
    }

    /** @param array<string, mixed> $input */
    public function updateOption(
        User $user,
        string $definitionPublicId,
        string $optionPublicId,
        array $input,
    ): CustomFieldOption {
        $store = $this->store($user, true);
        $definition = $this->definition($store, $definitionPublicId);
        $this->ensureSelectDefinition($definition);
        $option = $this->option($store, $definition, $optionPublicId);
        $data = $this->validateOption($this->normalizeOptionInput($input), false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($store, $option, $data): CustomFieldOption {
            if (array_key_exists('position', $data)) {
                $option->forceFill(['position' => $data['position']])->save();
            }
            if (isset($data['translations'])) {
                $this->syncOptionTranslations($store, $option, $data['translations']);
            }

            return $option->refresh()->load($this->optionRelations());
        });
    }

    public function deleteOption(
        User $user,
        string $definitionPublicId,
        string $optionPublicId,
    ): void {
        $store = $this->store($user, true);
        $definition = $this->definition($store, $definitionPublicId);
        $option = $this->option($store, $definition, $optionPublicId);
        if ($option->singleSelectValues()->exists() || $option->multiSelectValues()->exists()) {
            throw ValidationException::withMessages([
                'option' => ['This option is selected by a Product or Variant value and cannot be deleted.'],
            ]);
        }
        $option->delete();
    }

    /** @return list<ProductCustomFieldValue> */
    public function listValues(
        User $user,
        string $productPublicId,
        ?string $variantPublicId = null,
    ): array {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);
        $variant = $variantPublicId === null ? null : $this->variant($store, $product, $variantPublicId);

        return $this->valueScope($store, $product, $variant)
            ->with($this->valueRelations())
            ->orderBy('definition_id')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function showValue(
        User $user,
        string $productPublicId,
        string $definitionPublicId,
        ?string $variantPublicId = null,
    ): ProductCustomFieldValue {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);
        $variant = $variantPublicId === null ? null : $this->variant($store, $product, $variantPublicId);
        $definition = $this->definition($store, $definitionPublicId);

        return $this->value($store, $product, $definition, $variant)->load($this->valueRelations());
    }

    /** @param array<string, mixed> $input */
    public function setValue(
        User $user,
        string $productPublicId,
        string $definitionPublicId,
        array $input,
        ?string $variantPublicId = null,
    ): ProductCustomFieldValue {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $variant = $variantPublicId === null ? null : $this->variant($store, $product, $variantPublicId);
        $definition = $this->definition($store, $definitionPublicId);
        $this->ensureDefinitionApplies($definition, $product);
        $data = $this->validateTypedValue($definition, $this->normalizeValueInput($input));

        return DB::transaction(function () use ($store, $product, $variant, $definition, $data): ProductCustomFieldValue {
            $selectedOption = isset($data['option_id'])
                ? $this->option($store, $definition, (string) $data['option_id'])
                : null;
            $selectedOptionIds = isset($data['option_ids'])
                ? $this->optionIds($store, $definition, $data['option_ids'])
                : [];
            $value = ProductCustomFieldValue::query()->updateOrCreate(
                [
                    'store_id' => $store->getKey(),
                    'product_id' => $product->getKey(),
                    'variant_id' => $variant?->getKey(),
                    'definition_id' => $definition->getKey(),
                ],
                [
                    'value_number' => $data['value_number'] ?? null,
                    'value_boolean' => $data['value_boolean'] ?? null,
                    'value_date' => $data['value_date'] ?? null,
                    'value_option_id' => $selectedOption?->getKey(),
                ],
            );

            if (isset($data['translations'])) {
                $this->syncValueTranslations($store, $value, $data['translations']);
            } else {
                DB::table('product_custom_field_value_translations')
                    ->where('value_id', $value->getKey())
                    ->delete();
            }
            DB::table('product_custom_field_value_options')->where('value_id', $value->getKey())->delete();
            if ($selectedOptionIds !== []) {
                $now = now();
                DB::table('product_custom_field_value_options')->insert(array_map(
                    static fn (int $optionId): array => [
                        'store_id' => $store->getKey(),
                        'definition_id' => $definition->getKey(),
                        'value_id' => $value->getKey(),
                        'option_id' => $optionId,
                        'created_at' => $now,
                    ],
                    $selectedOptionIds,
                ));
            }

            return $value->refresh()->load($this->valueRelations());
        });
    }

    public function deleteValue(
        User $user,
        string $productPublicId,
        string $definitionPublicId,
        ?string $variantPublicId = null,
    ): void {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $variant = $variantPublicId === null ? null : $this->variant($store, $product, $variantPublicId);
        $definition = $this->definition($store, $definitionPublicId);
        $this->value($store, $product, $definition, $variant)->delete();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateDefinition(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'product_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'field_key' => [$required, 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/'],
            'field_type' => [$required, 'in:'.implode(',', self::TYPES)],
            'reference_object_type_id' => ['sometimes', 'nullable', 'ulid'],
            'is_required' => ['sometimes', 'boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.label' => ['required', 'string', 'max:255'],
            'translations.*.help_text' => ['sometimes', 'nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'options' => $creating ? ['sometimes', 'array', 'list', 'max:500'] : ['prohibited'],
            'options.*.position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'options.*.translations' => ['required', 'array', 'list', 'min:1'],
            'options.*.translations.*.locale' => ['required', 'string', 'max:35'],
            'options.*.translations.*.label' => ['required', 'string', 'max:255'],
            'options.*.translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateOption(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.label' => ['required', 'string', 'max:255'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateTypedValue(CustomFieldDefinition $definition, array $input): array
    {
        $data = Validator::make($input, [
            'value_number' => ['sometimes', 'numeric', 'decimal:0,4', 'between:-99999999999999.9999,99999999999999.9999'],
            'value_boolean' => ['sometimes', 'boolean'],
            'value_date' => ['sometimes', 'date_format:Y-m-d'],
            'option_id' => ['sometimes', 'ulid'],
            'option_ids' => ['sometimes', 'array', 'list', 'min:1', 'max:500'],
            'option_ids.*' => ['required', 'ulid', 'distinct'],
            'translations' => ['sometimes', 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.value_text' => ['required', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();
        $requiredKey = match ($definition->field_type) {
            'text', 'url' => 'translations',
            'number' => 'value_number',
            'boolean' => 'value_boolean',
            'date' => 'value_date',
            'select' => 'option_id',
            'multi_select' => 'option_ids',
            'object_reference', 'multi_object_reference' => throw ValidationException::withMessages([
                'definition' => ['Use the Custom Object reference endpoint for relational object values.'],
            ]),
            default => throw ValidationException::withMessages(['definition' => ['Unsupported custom-field type.']]),
        };
        $supplied = array_values(array_intersect(
            ['translations', 'value_number', 'value_boolean', 'value_date', 'option_id', 'option_ids'],
            array_keys($data),
        ));
        if ($supplied !== [$requiredKey]) {
            throw ValidationException::withMessages([
                'value' => ["The {$definition->field_type} field requires only [{$requiredKey}]."],
            ]);
        }
        if ($definition->field_type === 'url') {
            Validator::make($data, [
                'translations.*.value_text' => ['required', 'url:http,https', 'max:2048'],
            ])->validate();
        }

        return $data;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function normalizeDefinitionInput(array $input): array
    {
        foreach ([
            'productType' => 'product_type',
            'fieldKey' => 'field_key',
            'fieldType' => 'field_type',
            'referenceObjectTypeId' => 'reference_object_type_id',
            'isRequired' => 'is_required',
            'isFilterable' => 'is_filterable',
        ] as $from => $to) {
            if (array_key_exists($from, $input) && ! array_key_exists($to, $input)) {
                $input[$to] = $input[$from];
                unset($input[$from]);
            }
        }
        if (isset($input['translations'])) {
            $input['translations'] = array_map(fn (array $translation): array => $this->normalizeTranslation($translation), $input['translations']);
        }
        if (isset($input['options'])) {
            $input['options'] = array_map(fn (array $option): array => $this->normalizeOptionInput($option), $input['options']);
        }

        return $input;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function normalizeOptionInput(array $input): array
    {
        if (isset($input['translations'])) {
            $input['translations'] = array_map(fn (array $translation): array => $this->normalizeTranslation($translation), $input['translations']);
        }

        return $input;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function normalizeValueInput(array $input): array
    {
        foreach ([
            'valueNumber' => 'value_number',
            'valueBoolean' => 'value_boolean',
            'valueDate' => 'value_date',
            'optionId' => 'option_id',
            'optionIds' => 'option_ids',
        ] as $from => $to) {
            if (array_key_exists($from, $input) && ! array_key_exists($to, $input)) {
                $input[$to] = $input[$from];
                unset($input[$from]);
            }
        }
        if (isset($input['translations'])) {
            $input['translations'] = array_map(function (array $translation): array {
                if (array_key_exists('valueText', $translation) && ! array_key_exists('value_text', $translation)) {
                    $translation['value_text'] = $translation['valueText'];
                    unset($translation['valueText']);
                }

                return $this->normalizeTranslation($translation);
            }, $input['translations']);
        }

        return $input;
    }

    /** @param array<string, mixed> $translation @return array<string, mixed> */
    private function normalizeTranslation(array $translation): array
    {
        if (array_key_exists('helpText', $translation) && ! array_key_exists('help_text', $translation)) {
            $translation['help_text'] = $translation['helpText'];
            unset($translation['helpText']);
        }
        if (array_key_exists('lockIt', $translation) && ! array_key_exists('lock_it', $translation)) {
            $translation['lock_it'] = $translation['lockIt'];
            unset($translation['lockIt']);
        }

        return $translation;
    }

    /** @param list<array<string, mixed>> $options */
    private function ensureOptionsAllowed(string $fieldType, array $options): void
    {
        if ($options !== [] && ! in_array($fieldType, ['select', 'multi_select'], true)) {
            throw ValidationException::withMessages([
                'options' => ['Options are allowed only for select and multi-select fields.'],
            ]);
        }
    }

    private function ensureSelectDefinition(CustomFieldDefinition $definition): void
    {
        if (! in_array($definition->field_type, ['select', 'multi_select'], true)) {
            throw ValidationException::withMessages([
                'definition' => ['Options are allowed only for select and multi-select fields.'],
            ]);
        }
    }

    private function ensureDefinitionApplies(CustomFieldDefinition $definition, Product $product): void
    {
        if ($definition->product_type === null) {
            return;
        }
        $productTypeCode = $product->productType()->value('code');
        if ($productTypeCode !== $definition->product_type) {
            throw ValidationException::withMessages([
                'definition' => ['This custom field does not apply to the Product type.'],
            ]);
        }
    }

    private function ensureFieldKeyAvailable(Store $store, string $fieldKey, ?int $exceptId = null): void
    {
        $query = CustomFieldDefinition::query()
            ->where('store_id', $store->getKey())
            ->where('field_key', trim($fieldKey));
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'field_key' => ['The field key is already used in this Store.'],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function createOptionRow(
        Store $store,
        CustomFieldDefinition $definition,
        array $data,
    ): CustomFieldOption {
        $option = CustomFieldOption::query()->create([
            'store_id' => $store->getKey(),
            'definition_id' => $definition->getKey(),
            'position' => $data['position'] ?? 0,
        ]);
        $this->syncOptionTranslations($store, $option, $data['translations']);

        return $option->load($this->optionRelations());
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncDefinitionTranslations(
        Store $store,
        CustomFieldDefinition $definition,
        array $translations,
    ): void {
        $this->translations->sync(
            $store,
            'custom_field_definition_translations',
            'definition_id',
            (int) $definition->getKey(),
            $translations,
            ['label', 'help_text'],
            ['label'],
        );
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncOptionTranslations(Store $store, CustomFieldOption $option, array $translations): void
    {
        $this->translations->sync(
            $store,
            'custom_field_option_translations',
            'option_id',
            (int) $option->getKey(),
            $translations,
            ['label'],
            ['label'],
        );
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncValueTranslations(
        Store $store,
        ProductCustomFieldValue $value,
        array $translations,
    ): void {
        $this->translations->sync(
            $store,
            'product_custom_field_value_translations',
            'value_id',
            (int) $value->getKey(),
            $translations,
            ['value_text'],
            ['value_text'],
        );
    }

    /** @param list<string> $publicIds @return list<int> */
    private function optionIds(Store $store, CustomFieldDefinition $definition, array $publicIds): array
    {
        $options = CustomFieldOption::query()
            ->where('store_id', $store->getKey())
            ->where('definition_id', $definition->getKey())
            ->whereIn('public_id', $publicIds)
            ->get(['id', 'public_id']);
        if ($options->count() !== count($publicIds)) {
            throw ValidationException::withMessages([
                'option_ids' => ['Every option must belong to the selected custom-field definition.'],
            ]);
        }

        return $options->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
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

    private function definition(Store $store, string $publicId): CustomFieldDefinition
    {
        return CustomFieldDefinition::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function referenceObjectType(Store $store, string $publicId): CustomObjectType
    {
        return CustomObjectType::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    private function ensureReferenceConfiguration(
        array $data,
        string $fieldType,
        ?CustomFieldDefinition $existing = null,
    ): void {
        $isReference = in_array($fieldType, ['object_reference', 'multi_object_reference'], true);
        $reference = array_key_exists('reference_object_type_id', $data)
            ? $data['reference_object_type_id']
            : (array_key_exists('field_type', $data) && ! $isReference
                ? null
                : $existing?->reference_object_type_id);
        if ($isReference && $reference === null) {
            throw ValidationException::withMessages([
                'reference_object_type_id' => ['Reference Custom Fields require a Custom Object Type.'],
            ]);
        }
        if (! $isReference && array_key_exists('reference_object_type_id', $data) && $data['reference_object_type_id'] !== null) {
            throw ValidationException::withMessages([
                'reference_object_type_id' => ['Only object reference Custom Fields may specify a Custom Object Type.'],
            ]);
        }
    }

    private function option(
        Store $store,
        CustomFieldDefinition $definition,
        string $publicId,
    ): CustomFieldOption {
        return CustomFieldOption::query()
            ->where('store_id', $store->getKey())
            ->where('definition_id', $definition->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function value(
        Store $store,
        Product $product,
        CustomFieldDefinition $definition,
        ?ProductVariant $variant,
    ): ProductCustomFieldValue {
        return $this->valueScope($store, $product, $variant)
            ->where('definition_id', $definition->getKey())
            ->firstOrFail();
    }

    private function valueScope(
        Store $store,
        Product $product,
        ?ProductVariant $variant,
    ): Builder {
        return ProductCustomFieldValue::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->when(
                $variant === null,
                fn ($query) => $query->whereNull('variant_id'),
                fn ($query) => $query->where('variant_id', $variant->getKey()),
            );
    }

    /** @return list<string> */
    private function definitionRelations(): array
    {
        return [
            'translations',
            'options.definition',
            'options.translations',
            'referenceObjectType.translations',
        ];
    }

    /** @return list<string> */
    private function optionRelations(): array
    {
        return ['definition.translations', 'translations'];
    }

    /** @return list<string> */
    private function valueRelations(): array
    {
        return [
            'product',
            'variant',
            'definition.translations',
            'definition.options.definition',
            'definition.options.translations',
            'selectedOption.definition',
            'selectedOption.translations',
            'selectedOptions.definition',
            'selectedOptions.translations',
            'translations',
        ];
    }
}
