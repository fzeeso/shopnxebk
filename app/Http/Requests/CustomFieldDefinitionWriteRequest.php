<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CustomFieldDefinitionWriteRequest extends FormRequest
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
            'product_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'field_key' => [$required, 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/'],
            'field_type' => [$required, 'in:text,number,boolean,select,multi_select,date,url'],
            'is_required' => ['sometimes', 'boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.label' => ['required', 'string', 'max:255'],
            'translations.*.help_text' => ['sometimes', 'nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'options' => $this->isMethod('post')
                ? ['sometimes', 'array', 'list', 'max:500']
                : ['prohibited'],
            'options.*.position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'options.*.translations' => ['required', 'array', 'list', 'min:1'],
            'options.*.translations.*.locale' => ['required', 'string', 'max:35'],
            'options.*.translations.*.label' => ['required', 'string', 'max:255'],
            'options.*.translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
