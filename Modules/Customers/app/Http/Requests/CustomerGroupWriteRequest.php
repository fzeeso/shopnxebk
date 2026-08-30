<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerGroupCategoryAccess;

final class CustomerGroupWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $create = $this->isMethod('post');
        $required = $create ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'default_discount_percentage' => ['sometimes', 'numeric', 'decimal:0,4', 'between:0,100'],
            'discount_method' => [$required, 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'category_access_type' => ['sometimes', Rule::enum(CustomerGroupCategoryAccess::class)],
            'category_ids' => [$create ? 'sometimes' : 'prohibited', 'array', 'max:500'],
            'category_ids.*' => ['required', 'ulid', 'distinct'],
            'translations' => [$create ? 'required' : 'prohibited', 'array', 'min:1'],
            'translations.*.language_id' => ['required', 'ulid', 'distinct'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'legacy_id' => ['prohibited'],
        ];
    }
}
