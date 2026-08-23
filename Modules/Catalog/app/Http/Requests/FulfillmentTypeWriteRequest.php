<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FulfillmentTypeWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'code' => $creating
                ? ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('fulfillment_types', 'code')]
                : ['prohibited'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'translations' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'translations.*.locale' => [
                'required_with:translations',
                'string',
                'max:10',
                'distinct:strict',
                Rule::exists('languages', 'locale'),
            ],
            'translations.*.name' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
