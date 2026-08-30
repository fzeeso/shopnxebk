<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReplaceCollectionProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'products' => ['present', 'array', 'list', 'max:1000'],
            'products.*.product_id' => ['required', 'ulid', 'distinct'],
            'products.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'products.*.is_pinned' => ['sometimes', 'boolean'],
        ];
    }
}
