<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BrandWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'banner' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:/^(?:\/|https?:\/\/)/i'],
            'website_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'origin' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:35', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/'],
            'translations.*.name' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.seo_title' => ['nullable', 'string', 'max:255'],
            'translations.*.seo_description' => ['nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
