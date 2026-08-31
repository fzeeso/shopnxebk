<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CustomObjectEntryWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
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
        ];
    }
}
