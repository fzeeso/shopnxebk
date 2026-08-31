<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\CustomObjectEntry;
use Modules\Catalog\Models\CustomObjectField;
use Modules\Catalog\Models\CustomObjectType;
use Modules\Catalog\Models\CustomObjectValue;
use Modules\Catalog\Models\CustomObjectValueReference;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class CustomObjectManagementService
{
    public const FIELD_TYPES = [
        'text',
        'textarea',
        'rich_text',
        'number',
        'decimal',
        'boolean',
        'date',
        'datetime',
        'url',
        'email',
        'media',
        'image',
        'select',
        'multi_select',
        'object_reference',
        'multi_object_reference',
    ];

    public const LOCALIZABLE_FIELD_TYPES = [
        'text', 'textarea', 'rich_text', 'url', 'email', 'select', 'multi_select',
    ];

    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private LocalizedTranslationWriter $translations,
        private CustomObjectValueService $values,
    ) {}

    /** @param array<string, mixed> $filters */
    public function listTypes(User $user, array $filters): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $data = Validator::make($filters, [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'in:draft,active,archived'],
        ])->validate();
        $query = CustomObjectType::query()
            ->where('store_id', $store->getKey())
            ->with($this->typeRelations())
            ->withCount('entries');
        if (trim((string) ($data['search'] ?? '')) !== '') {
            $search = trim((string) $data['search']);
            $query->where(fn ($builder) => $builder
                ->where('handle', 'ILIKE', "%{$search}%")
                ->orWhereHas('translations', fn ($translation) => $translation
                    ->where('name', 'ILIKE', "%{$search}%")));
        }
        if (($data['status'] ?? null) !== null) {
            $query->where('status', $data['status']);
        }

        return $query->orderBy('handle')->orderBy('id')->paginate(
            (int) ($data['per_page'] ?? 25),
            ['*'],
            'page',
            (int) ($data['page'] ?? 1),
        );
    }

    public function showType(User $user, string $publicId): CustomObjectType
    {
        $store = $this->store($user, false);

        return $this->type($store, $publicId)->load($this->typeRelations())->loadCount('entries');
    }

    /** @param array<string, mixed> $input */
    public function createType(User $user, array $input): CustomObjectType
    {
        $store = $this->store($user, true);
        $data = $this->validateType($input, true);
        if (($data['is_system'] ?? false) && ! $user->isPlatformSuperAdmin()) {
            throw ValidationException::withMessages(['is_system' => ['Only a platform super administrator may create system types.']]);
        }
        $this->ensureTypeHandleAvailable($store, (string) $data['handle']);

        return DB::transaction(function () use ($store, $user, $data): CustomObjectType {
            $type = CustomObjectType::query()->create([
                'store_id' => $store->getKey(),
                'handle' => $data['handle'],
                'status' => $data['status'] ?? 'draft',
                'is_system' => $data['is_system'] ?? false,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);
            $this->syncTypeTranslations($store, $type, $data['translations']);
            foreach ($data['fields'] ?? [] as $field) {
                $this->createFieldRow($store, $type, $field);
            }

            return $type->load($this->typeRelations())->loadCount('entries');
        });
    }

    /** @param array<string, mixed> $input */
    public function updateType(User $user, string $publicId, array $input): CustomObjectType
    {
        $store = $this->store($user, true);
        $type = $this->type($store, $publicId);
        $data = $this->validateType($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        if (isset($data['handle'])) {
            $this->ensureTypeHandleAvailable($store, (string) $data['handle'], (int) $type->getKey());
        }
        if ($type->is_system && array_key_exists('is_system', $data) && ! $data['is_system']) {
            throw ValidationException::withMessages(['is_system' => ['System Custom Object Types cannot be converted to merchant types.']]);
        }
        if (($data['is_system'] ?? false) && ! $user->isPlatformSuperAdmin()) {
            throw ValidationException::withMessages(['is_system' => ['Only a platform super administrator may manage system types.']]);
        }

        return DB::transaction(function () use ($store, $user, $type, $data): CustomObjectType {
            $type->fill(array_intersect_key($data, array_flip(['handle', 'status', 'is_system'])));
            $type->updated_by = $user->getKey();
            $type->save();
            if (isset($data['translations'])) {
                $this->syncTypeTranslations($store, $type, $data['translations']);
            }

            return $type->refresh()->load($this->typeRelations())->loadCount('entries');
        });
    }

    public function deleteType(User $user, string $publicId): void
    {
        $store = $this->store($user, true);
        $type = $this->type($store, $publicId);
        if ($type->is_system) {
            throw ValidationException::withMessages(['type' => ['System Custom Object Types cannot be deleted.']]);
        }
        if ($type->references()->exists() || $type->customFieldDefinitions()->exists() || $type->referencingFields()->exists()) {
            throw ValidationException::withMessages(['type' => ['Archive this type: it is still configured or actively referenced.']]);
        }
        if (CustomObjectValueReference::query()->whereHas('entry', fn ($query) => $query
            ->where('custom_object_type_id', $type->getKey()))->exists()) {
            throw ValidationException::withMessages(['type' => ['Archive this type: one or more entries are referenced by another Custom Object.']]);
        }
        DB::transaction(function () use ($type): void {
            $entryIds = $type->entries()->pluck('id');
            if ($entryIds->isNotEmpty()) {
                CustomObjectValue::query()->whereIn('custom_object_entry_id', $entryIds)->delete();
                CustomObjectEntry::query()->whereIn('id', $entryIds)->delete();
            }
            CustomObjectField::query()->where('custom_object_type_id', $type->getKey())->delete();
            $type->delete();
        });
    }

    /** @return Collection<int, CustomObjectField> */
    public function listFields(User $user, string $typePublicId): Collection
    {
        $store = $this->store($user, false);
        $type = $this->type($store, $typePublicId);

        return CustomObjectField::query()
            ->where('store_id', $store->getKey())
            ->where('custom_object_type_id', $type->getKey())
            ->with($this->fieldRelations())
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    public function showField(User $user, string $publicId): CustomObjectField
    {
        $store = $this->store($user, false);

        return $this->field($store, $publicId)->load($this->fieldRelations());
    }

    /** @param array<string, mixed> $input */
    public function createField(User $user, string $typePublicId, array $input): CustomObjectField
    {
        $store = $this->store($user, true);
        $type = $this->type($store, $typePublicId);
        $data = $this->validateField($input, true);
        $this->ensureFieldConfiguration($data, (string) $data['field_type']);
        $this->ensureFieldHandleAvailable($type, (string) $data['handle']);
        if (($data['is_required'] ?? false) && $type->entries()->exists()) {
            throw ValidationException::withMessages(['is_required' => ['A required field cannot be added after entries exist without a default/backfill workflow.']]);
        }

        return DB::transaction(fn (): CustomObjectField => $this->createFieldRow($store, $type, $data));
    }

    /** @param array<string, mixed> $input */
    public function updateField(User $user, string $publicId, array $input): CustomObjectField
    {
        $store = $this->store($user, true);
        $field = $this->field($store, $publicId);
        $data = $this->validateField($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        $this->ensureFieldConfiguration($data, (string) ($data['field_type'] ?? $field->field_type), $field);
        if (isset($data['handle'])) {
            $this->ensureFieldHandleAvailable($field->type, (string) $data['handle'], (int) $field->getKey());
        }
        if (isset($data['field_type']) && $data['field_type'] !== $field->field_type && $field->values()->exists()) {
            throw ValidationException::withMessages(['field_type' => ['A field type cannot change after values exist.']]);
        }
        if (isset($data['reference_object_type_id'])) {
            $referenceType = $this->type($store, (string) $data['reference_object_type_id']);
            if ((int) $referenceType->getKey() !== (int) $field->reference_object_type_id
                && $field->values()->exists()) {
                throw ValidationException::withMessages([
                    'reference_object_type_id' => ['Clear existing values before changing the reference type.'],
                ]);
            }
        }
        if (($data['is_required'] ?? false) && ! $field->is_required && $field->type->entries()->whereDoesntHave(
            'values',
            fn ($query) => $query->where('custom_object_field_id', $field->getKey()),
        )->exists()) {
            throw ValidationException::withMessages(['is_required' => ['Every existing entry needs a value before this field can become required.']]);
        }

        return DB::transaction(function () use ($store, $field, $data): CustomObjectField {
            $attributes = array_intersect_key($data, array_flip([
                'handle', 'field_type', 'is_required', 'is_unique', 'is_localized', 'is_searchable',
                'is_filterable', 'sort_order', 'settings', 'validation_rules', 'status',
            ]));
            if (isset($data['reference_object_type_id'])) {
                $attributes['reference_object_type_id'] = $this->type($store, $data['reference_object_type_id'])->getKey();
            } elseif (isset($data['field_type']) && ! in_array($data['field_type'], ['object_reference', 'multi_object_reference'], true)) {
                $attributes['reference_object_type_id'] = null;
            }
            $field->fill($attributes)->save();
            if (isset($data['translations'])) {
                $this->syncFieldTranslations($store, $field, $data['translations']);
            }

            return $field->refresh()->load($this->fieldRelations());
        });
    }

    public function deleteField(User $user, string $publicId): void
    {
        $store = $this->store($user, true);
        $field = $this->field($store, $publicId);
        if ($field->values()->exists()) {
            throw ValidationException::withMessages(['field' => ['Archive this field: it already has entry values.']]);
        }
        $field->delete();
    }

    /** @param array<string, mixed> $filters */
    public function listEntries(User $user, string $typePublicId, array $filters): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $type = $this->type($store, $typePublicId);
        $data = Validator::make($filters, [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'in:draft,active,archived'],
        ])->validate();
        $query = CustomObjectEntry::query()
            ->where('store_id', $store->getKey())
            ->where('custom_object_type_id', $type->getKey())
            ->with($this->entryRelations());
        if (trim((string) ($data['search'] ?? '')) !== '') {
            $search = trim((string) $data['search']);
            $query->where(fn ($builder) => $builder
                ->where('handle', 'ILIKE', "%{$search}%")
                ->orWhereHas('translations', fn ($translation) => $translation
                    ->where('name', 'ILIKE', "%{$search}%")));
        }
        if (($data['status'] ?? null) !== null) {
            $query->where('status', $data['status']);
        }

        return $query->orderBy('sort_order')->orderBy('id')->paginate(
            (int) ($data['per_page'] ?? 25),
            ['*'],
            'page',
            (int) ($data['page'] ?? 1),
        );
    }

    public function showEntry(User $user, string $publicId): CustomObjectEntry
    {
        $store = $this->store($user, false);

        return $this->entry($store, $publicId)->load($this->entryRelations());
    }

    /** @param array<string, mixed> $input */
    public function createEntry(User $user, string $typePublicId, array $input): CustomObjectEntry
    {
        $store = $this->store($user, true);
        $type = $this->type($store, $typePublicId);
        $data = $this->validateEntry($input, true);
        $this->ensureEntryHandleAvailable($type, (string) $data['handle']);

        return DB::transaction(function () use ($store, $user, $type, $data): CustomObjectEntry {
            $entry = CustomObjectEntry::query()->create([
                'store_id' => $store->getKey(),
                'custom_object_type_id' => $type->getKey(),
                'handle' => $data['handle'],
                'status' => $data['status'] ?? 'draft',
                'sort_order' => $data['sort_order'] ?? 0,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);
            $this->syncEntryTranslations($store, $entry, $data['translations']);
            $this->values->sync($store, $entry, $data['values'] ?? [], true);

            return $entry->load($this->entryRelations());
        });
    }

    /** @param array<string, mixed> $input */
    public function updateEntry(User $user, string $publicId, array $input): CustomObjectEntry
    {
        $store = $this->store($user, true);
        $entry = $this->entry($store, $publicId);
        $data = $this->validateEntry($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }
        if (isset($data['handle'])) {
            $this->ensureEntryHandleAvailable($entry->type, (string) $data['handle'], (int) $entry->getKey());
        }

        return DB::transaction(function () use ($store, $user, $entry, $data): CustomObjectEntry {
            $entry->fill(array_intersect_key($data, array_flip(['handle', 'status', 'sort_order'])));
            $entry->updated_by = $user->getKey();
            $entry->save();
            if (isset($data['translations'])) {
                $this->syncEntryTranslations($store, $entry, $data['translations']);
            }
            if (isset($data['values'])) {
                $this->values->sync($store, $entry, $data['values'], false);
            }

            return $entry->refresh()->load($this->entryRelations());
        });
    }

    public function deleteEntry(User $user, string $publicId): void
    {
        $store = $this->store($user, true);
        $entry = $this->entry($store, $publicId);
        if ($entry->references()->exists() || $entry->valueReferences()->exists()) {
            throw ValidationException::withMessages(['entry' => ['Archive this entry: it is actively referenced.']]);
        }
        DB::transaction(function () use ($entry): void {
            $entry->values()->delete();
            $entry->delete();
        });
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateType(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'handle' => [$required, 'string', 'max:150', 'regex:/^[a-z][a-z0-9-]*$/'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'is_system' => ['sometimes', 'boolean'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'fields' => $creating ? ['sometimes', 'array', 'list', 'max:100'] : ['prohibited'],
            'fields.*' => ['array'],
        ])->validate();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateField(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $data = Validator::make($input, [
            'handle' => [$required, 'string', 'max:150', 'regex:/^[a-z][a-z0-9-]*$/'],
            'field_type' => [$required, 'in:'.implode(',', self::FIELD_TYPES)],
            'is_required' => ['sometimes', 'boolean'],
            'is_unique' => ['sometimes', 'boolean'],
            'is_localized' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'reference_object_type_id' => ['sometimes', 'nullable', 'ulid'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'settings.options' => ['sometimes', 'array', 'list', 'max:500'],
            'settings.options.*' => ['required', 'string', 'max:255', 'distinct'],
            'validation_rules' => ['sometimes', 'nullable', 'array'],
            'validation_rules.min_length' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'validation_rules.max_length' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'validation_rules.min' => ['sometimes', 'numeric'],
            'validation_rules.max' => ['sometimes', 'numeric'],
            'validation_rules.regex' => ['sometimes', 'string', 'max:500'],
            'status' => ['sometimes', 'in:active,archived'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.label' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
            'translations.*.help_text' => ['sometimes', 'nullable', 'string'],
            'translations.*.placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();

        if (isset($data['validation_rules']['min_length'], $data['validation_rules']['max_length'])
            && $data['validation_rules']['min_length'] > $data['validation_rules']['max_length']) {
            throw ValidationException::withMessages([
                'validation_rules.max_length' => ['The maximum length must be greater than or equal to the minimum length.'],
            ]);
        }
        if (isset($data['validation_rules']['min'], $data['validation_rules']['max'])
            && $data['validation_rules']['min'] > $data['validation_rules']['max']) {
            throw ValidationException::withMessages([
                'validation_rules.max' => ['The maximum must be greater than or equal to the minimum.'],
            ]);
        }
        if (isset($data['validation_rules']['regex'])
            && @preg_match((string) $data['validation_rules']['regex'], '') === false) {
            throw ValidationException::withMessages([
                'validation_rules.regex' => ['The regular expression is invalid.'],
            ]);
        }

        return $data;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validateEntry(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'handle' => [$required, 'string', 'max:150', 'regex:/^[a-z][a-z0-9-]*$/'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'values' => ['sometimes', 'array', 'list', 'max:100'],
            'values.*.field_id' => ['required', 'ulid'],
            'values.*.delete' => ['sometimes', 'boolean'],
            'values.*.value_text' => ['sometimes', 'string'],
            'values.*.value_number' => ['sometimes', 'numeric'],
            'values.*.value_boolean' => ['sometimes', 'boolean'],
            'values.*.value_date' => ['sometimes', 'date_format:Y-m-d'],
            'values.*.value_datetime' => ['sometimes', 'date'],
            'values.*.value_json' => ['sometimes', 'array'],
            'values.*.media_id' => ['sometimes', 'ulid'],
            'values.*.entry_ids' => ['sometimes', 'array', 'list', 'max:100'],
            'values.*.entry_ids.*' => ['required', 'ulid', 'distinct'],
            'values.*.translations' => ['sometimes', 'array', 'list', 'min:1'],
            'values.*.translations.*.locale' => ['required', 'string', 'max:35'],
            'values.*.translations.*.value_text' => ['sometimes', 'string'],
            'values.*.translations.*.value_json' => ['sometimes', 'array'],
            'values.*.translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param array<string, mixed> $data */
    private function createFieldRow(Store $store, CustomObjectType $type, array $data): CustomObjectField
    {
        $data = $this->validateField($data, true);
        $this->ensureFieldConfiguration($data, (string) $data['field_type']);
        $this->ensureFieldHandleAvailable($type, (string) $data['handle']);
        $referenceTypeId = isset($data['reference_object_type_id'])
            ? $this->type($store, $data['reference_object_type_id'])->getKey()
            : null;
        $field = CustomObjectField::query()->create([
            'store_id' => $store->getKey(),
            'custom_object_type_id' => $type->getKey(),
            'handle' => $data['handle'],
            'field_type' => $data['field_type'],
            'is_required' => $data['is_required'] ?? false,
            'is_unique' => $data['is_unique'] ?? false,
            'is_localized' => $data['is_localized'] ?? false,
            'is_searchable' => $data['is_searchable'] ?? false,
            'is_filterable' => $data['is_filterable'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
            'reference_object_type_id' => $referenceTypeId,
            'settings' => $data['settings'] ?? null,
            'validation_rules' => $data['validation_rules'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
        $this->syncFieldTranslations($store, $field, $data['translations']);

        return $field->load($this->fieldRelations());
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncTypeTranslations(Store $store, CustomObjectType $type, array $translations): void
    {
        $this->translations->sync($store, 'custom_object_type_translations', 'custom_object_type_id', (int) $type->getKey(), $translations, ['name', 'description'], ['name']);
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncFieldTranslations(Store $store, CustomObjectField $field, array $translations): void
    {
        $this->translations->sync($store, 'custom_object_field_translations', 'custom_object_field_id', (int) $field->getKey(), $translations, ['label', 'description', 'help_text', 'placeholder'], ['label']);
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncEntryTranslations(Store $store, CustomObjectEntry $entry, array $translations): void
    {
        $this->translations->sync($store, 'custom_object_entry_translations', 'custom_object_entry_id', (int) $entry->getKey(), $translations, ['name', 'description'], ['name']);
    }

    private function ensureTypeHandleAvailable(Store $store, string $handle, ?int $except = null): void
    {
        $query = CustomObjectType::query()->where('store_id', $store->getKey())->where('handle', $handle);
        if ($except !== null) {
            $query->whereKeyNot($except);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['handle' => ['The handle is already used by this Store.']]);
        }
    }

    private function ensureFieldHandleAvailable(CustomObjectType $type, string $handle, ?int $except = null): void
    {
        $query = CustomObjectField::query()->where('custom_object_type_id', $type->getKey())->where('handle', $handle);
        if ($except !== null) {
            $query->whereKeyNot($except);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['handle' => ['The handle is already used by this Custom Object Type.']]);
        }
    }

    private function ensureEntryHandleAvailable(CustomObjectType $type, string $handle, ?int $except = null): void
    {
        $query = CustomObjectEntry::query()->where('custom_object_type_id', $type->getKey())->where('handle', $handle);
        if ($except !== null) {
            $query->whereKeyNot($except);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['handle' => ['The handle is already used by this Custom Object Type.']]);
        }
    }

    /** @param array<string, mixed> $data */
    private function ensureFieldConfiguration(
        array $data,
        string $fieldType,
        ?CustomObjectField $existing = null,
    ): void {
        $isReference = in_array($fieldType, ['object_reference', 'multi_object_reference'], true);
        $reference = array_key_exists('reference_object_type_id', $data)
            ? $data['reference_object_type_id']
            : (array_key_exists('field_type', $data) && ! $isReference
                ? null
                : $existing?->reference_object_type_id);
        if ($isReference && $reference === null) {
            throw ValidationException::withMessages([
                'reference_object_type_id' => ['Reference fields require a Custom Object Type.'],
            ]);
        }
        if (! $isReference && $reference !== null) {
            throw ValidationException::withMessages([
                'reference_object_type_id' => ['Only reference fields may specify a reference Custom Object Type.'],
            ]);
        }
        $localized = array_key_exists('is_localized', $data)
            ? (bool) $data['is_localized']
            : (bool) $existing?->is_localized;
        if ($localized && ! in_array($fieldType, self::LOCALIZABLE_FIELD_TYPES, true)) {
            throw ValidationException::withMessages(['is_localized' => ['This field type cannot be localized.']]);
        }
        $unique = array_key_exists('is_unique', $data)
            ? (bool) $data['is_unique']
            : (bool) $existing?->is_unique;
        if ($unique && in_array($fieldType, ['multi_select', 'multi_object_reference'], true)) {
            throw ValidationException::withMessages(['is_unique' => ['Multi-value fields cannot be marked unique.']]);
        }
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage ? $this->access->ensureCanManageProducts($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function type(Store $store, string $publicId): CustomObjectType
    {
        return CustomObjectType::query()->where('store_id', $store->getKey())->where('public_id', $publicId)->firstOrFail();
    }

    private function field(Store $store, string $publicId): CustomObjectField
    {
        return CustomObjectField::query()->where('store_id', $store->getKey())->where('public_id', $publicId)->with('type')->firstOrFail();
    }

    private function entry(Store $store, string $publicId): CustomObjectEntry
    {
        return CustomObjectEntry::query()->where('store_id', $store->getKey())->where('public_id', $publicId)->with('type')->firstOrFail();
    }

    /** @return list<string> */
    private function typeRelations(): array
    {
        return ['store', 'translations', 'fields.translations', 'fields.referenceObjectType.translations', 'creator', 'updater'];
    }

    /** @return list<string> */
    private function fieldRelations(): array
    {
        return ['store', 'type.translations', 'translations', 'referenceObjectType.translations'];
    }

    /** @return list<string> */
    private function entryRelations(): array
    {
        return [
            'store', 'type.translations', 'translations', 'creator', 'updater',
            'values.field.translations', 'values.field.referenceObjectType.translations',
            'values.translations', 'values.media', 'values.referencedEntries.translations',
        ];
    }
}
