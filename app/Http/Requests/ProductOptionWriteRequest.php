<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductOptionWriteRequest extends FormRequest
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
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name' => ['required', 'string', 'max:100'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'values' => $this->isMethod('post')
                ? ['sometimes', 'array', 'list', 'max:100']
                : ['prohibited'],
            'values.*.position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'values.*.translations' => ['required', 'array', 'list', 'min:1'],
            'values.*.translations.*.locale' => ['required', 'string', 'max:35'],
            'values.*.translations.*.value' => ['required', 'string', 'max:100'],
            'values.*.translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
