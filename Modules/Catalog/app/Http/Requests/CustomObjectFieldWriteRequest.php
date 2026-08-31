<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Services\CustomObjectManagementService;

final class CustomObjectFieldWriteRequest extends FormRequest
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
            'field_type' => [$required, Rule::in(CustomObjectManagementService::FIELD_TYPES)],
            'is_required' => ['sometimes', 'boolean'],
            'is_unique' => ['sometimes', 'boolean'],
            'is_localized' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'reference_object_type_id' => ['sometimes', 'nullable', 'ulid'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'validation_rules' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'in:active,archived'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.label' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
            'translations.*.help_text' => ['sometimes', 'nullable', 'string'],
            'translations.*.placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
