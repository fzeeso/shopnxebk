<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReplaceCustomerGroupCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'category_ids' => ['required', 'array', 'max:500'],
            'category_ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }
}
