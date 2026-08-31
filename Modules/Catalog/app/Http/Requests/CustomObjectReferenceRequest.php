<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CustomObjectReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $generic = ! $this->routeIs('store.products.custom-object-references.*');

        return [
            'source_type' => [$generic ? 'required' : 'sometimes', 'in:product,collection,category,brand,page'],
            'source_id' => [$generic ? 'required' : 'sometimes', 'ulid'],
            'definition_id' => ['sometimes', 'nullable', 'ulid'],
            'entry_ids' => [$this->isMethod('put') ? 'required' : 'sometimes', 'array', 'list', 'max:100'],
            'entry_ids.*' => ['required', 'ulid', 'distinct'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:35'],
        ];
    }
}
