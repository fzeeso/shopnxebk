<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Stores\Enums\PageType;

final class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'parent_id' => ['sometimes', 'nullable', 'string', 'ulid'],
            'page_type' => ['sometimes', Rule::enum(PageType::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'layout_key' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'is_homepage' => ['sometimes', 'boolean'],
            'customers_only' => ['sometimes', 'boolean'],
            'seo_enabled' => ['sometimes', 'boolean'],
            'external_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
            'feed_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
            'contact_email' => ['sometimes', 'nullable', 'email:rfc', 'max:320'],
            'contact_fields' => ['sometimes', 'array', 'max:50'],
            'contact_fields.*' => ['array'],
        ];
    }
}
