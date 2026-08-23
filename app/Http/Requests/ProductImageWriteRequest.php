<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductImageWriteRequest extends FormRequest
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
            'variant_id' => ['sometimes', 'nullable', 'ulid'],
            'url' => [$required, 'string', 'max:500', 'regex:/^(?:\/|https?:\/\/)/i'],
            'width' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'height' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => ['sometimes', 'array', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:35', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/'],
            'translations.*.alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
