<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CollectionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:35'],
            'parent_id' => ['sometimes', 'nullable', 'ulid'],
            'root_only' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'collection_type' => ['sometimes', 'nullable', 'in:manual,rule_based,ai_generated'],
            'sort_by' => ['sometimes', 'in:sort_order,created_at,updated_at'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
