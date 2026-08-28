<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductCustomFieldValueWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'value_number' => ['sometimes', 'numeric', 'decimal:0,4', 'between:-99999999999999.9999,99999999999999.9999'],
            'value_boolean' => ['sometimes', 'boolean'],
            'value_date' => ['sometimes', 'date_format:Y-m-d'],
            'option_id' => ['sometimes', 'ulid'],
            'option_ids' => ['sometimes', 'array', 'list', 'max:500'],
            'option_ids.*' => ['required', 'ulid', 'distinct'],
            'translations' => ['sometimes', 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.value_text' => ['required', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
