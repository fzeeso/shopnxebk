<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Models\CustomObjectEntry;
use Modules\Catalog\Models\CustomObjectField;
use Modules\Catalog\Models\CustomObjectValue;
use Modules\Stores\Models\Store;

final readonly class CustomObjectValueService
{
    public function __construct(private LocalizedTranslationWriter $translations) {}

    /** @param list<array<string, mixed>> $commands */
    public function sync(Store $store, CustomObjectEntry $entry, array $commands, bool $creating): void
    {
        $fields = CustomObjectField::query()
            ->where('store_id', $store->getKey())
            ->where('custom_object_type_id', $entry->custom_object_type_id)
            ->where('status', 'active')
            ->get()
            ->keyBy('public_id');
        $seen = [];

        foreach ($commands as $index => $command) {
            $fieldId = (string) ($command['field_id'] ?? '');
            /** @var CustomObjectField|null $field */
            $field = $fields->get($fieldId);
            if ($field === null) {
                throw ValidationException::withMessages([
                    "values.{$index}.field_id" => ['The field must be an active field on this Custom Object Type.'],
                ]);
            }
            if (isset($seen[$fieldId])) {
                throw ValidationException::withMessages(['values' => ['Each field may appear only once.']]);
            }
            $seen[$fieldId] = true;

            $existing = CustomObjectValue::query()
                ->where('store_id', $store->getKey())
                ->where('custom_object_entry_id', $entry->getKey())
                ->where('custom_object_field_id', $field->getKey())
                ->first();
            if ((bool) ($command['delete'] ?? false)) {
                if ($field->is_required) {
                    throw ValidationException::withMessages([
                        "values.{$index}.delete" => ['A required Custom Object field cannot be cleared.'],
                    ]);
                }
                $existing?->delete();

                continue;
            }

            $data = $this->validate($store, $field, $command, $index);
            $value = CustomObjectValue::query()->updateOrCreate(
                [
                    'store_id' => $store->getKey(),
                    'custom_object_entry_id' => $entry->getKey(),
                    'custom_object_field_id' => $field->getKey(),
                ],
                [
                    'custom_object_type_id' => $entry->custom_object_type_id,
                    'value_text' => $data['value_text'] ?? null,
                    'value_number' => $data['value_number'] ?? null,
                    'value_boolean' => $data['value_boolean'] ?? null,
                    'value_date' => $data['value_date'] ?? null,
                    'value_datetime' => $data['value_datetime'] ?? null,
                    'value_json' => $data['value_json'] ?? null,
                    'value_media_id' => $data['value_media_id'] ?? null,
                ],
            );

            $this->ensureUnique($field, $value, $data, $index);
            $this->syncTranslations($store, $field, $value, $data);
            $this->syncReferences($store, $value, $data['reference_entry_ids'] ?? []);
        }

        if ($creating || $commands !== []) {
            $this->ensureRequiredValues($store, $entry);
        }
    }

    /** @param array<string, mixed> $command @return array<string, mixed> */
    private function validate(Store $store, CustomObjectField $field, array $command, int $index): array
    {
        $data = Validator::make($command, [
            'field_id' => ['required', 'ulid'],
            'value_text' => ['sometimes', 'string'],
            'value_number' => ['sometimes', 'numeric'],
            'value_boolean' => ['sometimes', 'boolean'],
            'value_date' => ['sometimes', 'date_format:Y-m-d'],
            'value_datetime' => ['sometimes', 'date'],
            'value_json' => ['sometimes', 'array'],
            'media_id' => ['sometimes', 'ulid'],
            'entry_ids' => ['sometimes', 'array', 'list', 'min:1', 'max:100'],
            'entry_ids.*' => ['required', 'ulid', 'distinct'],
            'translations' => ['sometimes', 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.value_text' => ['sometimes', 'string'],
            'translations.*.value_json' => ['sometimes', 'array'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ])->validate();

        $expected = match ($field->field_type) {
            'text', 'textarea', 'rich_text', 'url', 'email' => 'value_text',
            'number', 'decimal' => 'value_number',
            'boolean' => 'value_boolean',
            'date' => 'value_date',
            'datetime' => 'value_datetime',
            'media', 'image' => 'media_id',
            'select', 'multi_select' => 'value_json',
            'object_reference', 'multi_object_reference' => 'entry_ids',
            default => throw ValidationException::withMessages(['field' => ['Unsupported Custom Object field type.']]),
        };
        if ($field->is_localized) {
            $expected = 'translations';
        }
        $supplied = array_values(array_intersect(
            ['value_text', 'value_number', 'value_boolean', 'value_date', 'value_datetime', 'value_json', 'media_id', 'entry_ids', 'translations'],
            array_keys($data),
        ));
        if ($supplied !== [$expected]) {
            throw ValidationException::withMessages([
                "values.{$index}" => ["The {$field->field_type} field requires only [{$expected}]."],
            ]);
        }

        if (isset($data['value_text'])) {
            $this->validateText($field, (string) $data['value_text'], "values.{$index}.value_text");
        }
        if (isset($data['value_number'])) {
            $this->validateNumber($field, $data['value_number'], "values.{$index}.value_number");
        }
        if (isset($data['value_json'])) {
            $this->validateSelection($field, $data['value_json'], "values.{$index}.value_json");
        }
        if (isset($data['translations'])) {
            foreach ($data['translations'] as $translationIndex => $translation) {
                $translationKey = in_array($field->field_type, ['select', 'multi_select'], true)
                    ? 'value_json'
                    : 'value_text';
                $present = array_values(array_intersect(['value_text', 'value_json'], array_keys($translation)));
                if ($present !== [$translationKey]) {
                    throw ValidationException::withMessages([
                        "values.{$index}.translations.{$translationIndex}" => ["This localized field requires [{$translationKey}]."],
                    ]);
                }
                $translationKey === 'value_text'
                    ? $this->validateText($field, (string) $translation[$translationKey], "values.{$index}.translations.{$translationIndex}.value_text")
                    : $this->validateSelection($field, $translation[$translationKey], "values.{$index}.translations.{$translationIndex}.value_json");
            }
        }
        if (isset($data['media_id'])) {
            $data['value_media_id'] = Media::query()
                ->where('store_id', $store->getKey())
                ->where('public_id', $data['media_id'])
                ->firstOrFail()
                ->getKey();
            unset($data['media_id']);
        }
        if (isset($data['entry_ids'])) {
            $data['reference_entry_ids'] = $this->referenceEntryIds($store, $field, $data['entry_ids'], $index);
            unset($data['entry_ids']);
        }

        return $data;
    }

    private function validateText(CustomObjectField $field, string $value, string $key): void
    {
        if ($field->is_required && trim($value) === '') {
            throw ValidationException::withMessages([$key => ['A required field cannot be blank.']]);
        }
        $rules = ['string'];
        $validation = $field->validation_rules ?? [];
        if (isset($validation['min_length'])) {
            $rules[] = 'min:'.(int) $validation['min_length'];
        }
        if (isset($validation['max_length'])) {
            $rules[] = 'max:'.(int) $validation['max_length'];
        }
        if (isset($validation['regex'])) {
            $rules[] = 'regex:'.$validation['regex'];
        }
        if ($field->field_type === 'url') {
            $rules[] = 'url:http,https';
            $rules[] = 'max:2048';
        } elseif ($field->field_type === 'email') {
            $rules[] = 'email:rfc';
            $rules[] = 'max:320';
        }

        $validator = Validator::make(['value' => $value], ['value' => $rules]);
        if ($validator->fails()) {
            throw ValidationException::withMessages([$key => $validator->errors()->get('value')]);
        }
    }

    private function validateNumber(CustomObjectField $field, mixed $value, string $key): void
    {
        $rules = ['numeric'];
        $validation = $field->validation_rules ?? [];
        if (isset($validation['min'])) {
            $rules[] = 'min:'.$validation['min'];
        }
        if (isset($validation['max'])) {
            $rules[] = 'max:'.$validation['max'];
        }
        if ($field->field_type === 'number') {
            $rules[] = 'integer';
        }

        $validator = Validator::make(['value' => $value], ['value' => $rules]);
        if ($validator->fails()) {
            throw ValidationException::withMessages([$key => $validator->errors()->get('value')]);
        }
    }

    /** @param array<mixed> $selection */
    private function validateSelection(CustomObjectField $field, array $selection, string $key): void
    {
        if (count(array_unique($selection, SORT_REGULAR)) !== count($selection)) {
            throw ValidationException::withMessages([$key => ['Selected values must be distinct.']]);
        }
        if ($field->field_type === 'select' && (count($selection) !== 1 || ! is_string($selection[0] ?? null))) {
            throw ValidationException::withMessages([$key => ['Select fields require an array containing exactly one string value.']]);
        }
        if ($field->field_type === 'multi_select' && ($selection === [] || array_filter($selection, 'is_string') !== $selection)) {
            throw ValidationException::withMessages([$key => ['Multi-select fields require a non-empty string list.']]);
        }
        $options = $field->settings['options'] ?? null;
        if (is_array($options) && array_diff($selection, $options) !== []) {
            throw ValidationException::withMessages([$key => ['Every selected value must be configured in settings.options.']]);
        }
    }

    /** @param list<string> $publicIds @return list<int> */
    private function referenceEntryIds(Store $store, CustomObjectField $field, array $publicIds, int $index): array
    {
        if ($field->field_type === 'object_reference' && count($publicIds) !== 1) {
            throw ValidationException::withMessages([
                "values.{$index}.entry_ids" => ['Object reference fields require exactly one entry.'],
            ]);
        }
        $entries = CustomObjectEntry::query()
            ->where('store_id', $store->getKey())
            ->where('custom_object_type_id', $field->reference_object_type_id)
            ->where('status', 'active')
            ->whereIn('public_id', $publicIds)
            ->get(['id', 'public_id'])
            ->keyBy('public_id');
        if ($entries->count() !== count($publicIds)) {
            throw ValidationException::withMessages([
                "values.{$index}.entry_ids" => ['Every entry must be active, Store-owned, and belong to the configured reference type.'],
            ]);
        }

        return array_map(static fn (string $id): int => (int) $entries[$id]->getKey(), $publicIds);
    }

    /** @param array<string, mixed> $data */
    private function ensureUnique(CustomObjectField $field, CustomObjectValue $value, array $data, int $index): void
    {
        if (! $field->is_unique) {
            return;
        }
        if (isset($data['translations'])) {
            foreach ($data['translations'] as $translation) {
                $query = DB::table('custom_object_value_translations')
                    ->join('custom_object_values', 'custom_object_values.id', '=', 'custom_object_value_translations.custom_object_value_id')
                    ->where('custom_object_values.custom_object_field_id', $field->getKey())
                    ->where('custom_object_value_translations.locale', $translation['locale'])
                    ->where('custom_object_value_translations.custom_object_value_id', '<>', $value->getKey());
                isset($translation['value_text'])
                    ? $query->where('custom_object_value_translations.value_text', $translation['value_text'])
                    : $query->whereRaw('custom_object_value_translations.value_json = ?::jsonb', [json_encode($translation['value_json'], JSON_THROW_ON_ERROR)]);
                if ($query->exists()) {
                    throw ValidationException::withMessages(["values.{$index}" => ['This localized field value must be unique.']]);
                }
            }

            return;
        }

        $column = collect([
            'value_text', 'value_number', 'value_boolean', 'value_date', 'value_datetime', 'value_json', 'value_media_id',
        ])->first(fn (string $candidate): bool => array_key_exists($candidate, $data));
        $query = CustomObjectValue::query()
            ->where('custom_object_field_id', $field->getKey())
            ->whereKeyNot($value->getKey());
        if ($column !== null) {
            $column === 'value_json'
                ? $query->whereRaw('value_json = ?::jsonb', [json_encode($data[$column], JSON_THROW_ON_ERROR)])
                : $query->where($column, $data[$column]);
        } elseif (isset($data['reference_entry_ids'][0])) {
            $entryId = $data['reference_entry_ids'][0];
            $query->whereHas('referencedEntries', fn (Builder $builder) => $builder->whereKey($entryId));
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(["values.{$index}" => ['This field value must be unique.']]);
        }
    }

    /** @param array<string, mixed> $data */
    private function syncTranslations(Store $store, CustomObjectField $field, CustomObjectValue $value, array $data): void
    {
        if (! $field->is_localized) {
            DB::table('custom_object_value_translations')->where('custom_object_value_id', $value->getKey())->delete();

            return;
        }
        $translationField = in_array($field->field_type, ['select', 'multi_select'], true)
            ? 'value_json'
            : 'value_text';
        $this->translations->sync(
            $store,
            'custom_object_value_translations',
            'custom_object_value_id',
            (int) $value->getKey(),
            $data['translations'],
            [$translationField],
            [$translationField],
        );
    }

    /** @param list<int> $entryIds */
    private function syncReferences(Store $store, CustomObjectValue $value, array $entryIds): void
    {
        DB::table('custom_object_value_references')->where('custom_object_value_id', $value->getKey())->delete();
        if ($entryIds === []) {
            return;
        }
        $now = now();
        DB::table('custom_object_value_references')->insert(array_map(
            static fn (int $entryId, int $index): array => [
                'store_id' => $store->getKey(),
                'custom_object_value_id' => $value->getKey(),
                'custom_object_entry_id' => $entryId,
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $entryIds,
            array_keys($entryIds),
        ));
    }

    private function ensureRequiredValues(Store $store, CustomObjectEntry $entry): void
    {
        $requiredFieldIds = CustomObjectField::query()
            ->where('store_id', $store->getKey())
            ->where('custom_object_type_id', $entry->custom_object_type_id)
            ->where('status', 'active')
            ->where('is_required', true)
            ->pluck('id');
        if ($requiredFieldIds->isEmpty()) {
            return;
        }
        $stored = CustomObjectValue::query()
            ->where('custom_object_entry_id', $entry->getKey())
            ->whereIn('custom_object_field_id', $requiredFieldIds)
            ->pluck('custom_object_field_id');
        if ($stored->count() !== $requiredFieldIds->count()) {
            throw ValidationException::withMessages(['values' => ['Every required Custom Object field must have a value.']]);
        }
    }
}
