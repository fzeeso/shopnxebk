<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Stores\Enums\PageType;

final class CreatePageRequest extends FormRequest
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
            'page_type' => ['required', Rule::enum(PageType::class)],
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
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.language_id' => ['required', 'string', 'ulid', 'distinct'],
            'translations.*.title' => ['required', 'string', 'max:250'],
            'translations.*.slug' => ['required', 'string', 'max:250', 'regex:/^[\pL\pN]+(?:-[\pL\pN]+)*$/u'],
            'translations.*.content' => ['nullable', 'string', 'max:1000000'],
            'translations.*.summary' => ['nullable', 'string', 'max:5000'],
            'translations.*.seo_title' => ['nullable', 'string', 'max:250'],
            'translations.*.seo_description' => ['nullable', 'string', 'max:1000'],
            'translations.*.seo_keywords' => ['nullable', 'string', 'max:1000'],
            'translations.*.search_keywords' => ['nullable', 'string', 'max:1000'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
