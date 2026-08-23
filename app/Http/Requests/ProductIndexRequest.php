<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductIndexRequest extends FormRequest
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
            'sku' => ['sometimes', 'nullable', 'string', 'max:250'],
            'status' => ['sometimes', 'nullable', 'in:draft,active,archived'],
            'fulfillment_type' => ['sometimes', 'nullable', 'in:physical,digital,software,service'],
            'condition' => ['sometimes', 'nullable', 'in:New,Used,Refurbished'],
            'is_featured' => ['sometimes', 'boolean'],
            'brand_id' => ['sometimes', 'nullable', 'ulid'],
            'category_id' => ['sometimes', 'nullable', 'ulid'],
            'sort_by' => ['sometimes', 'in:created_at,updated_at,status,published_at,price,sort_order'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
