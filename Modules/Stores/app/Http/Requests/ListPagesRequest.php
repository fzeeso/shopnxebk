<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Stores\Enums\PageStatus;
use Modules\Stores\Enums\PageType;

final class ListPagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::enum(PageStatus::class)],
            'page_type' => ['sometimes', Rule::enum(PageType::class)],
            'parent_id' => ['sometimes', 'nullable', 'string', 'ulid'],
            'root_only' => ['sometimes', 'boolean'],
            'language_id' => ['sometimes', 'string', 'ulid'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
