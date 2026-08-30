<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertPageTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'],
            'slug' => ['required', 'string', 'max:250', 'regex:/^[\pL\pN]+(?:-[\pL\pN]+)*$/u'],
            'content' => ['nullable', 'string', 'max:1000000'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'seo_title' => ['nullable', 'string', 'max:250'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'seo_keywords' => ['nullable', 'string', 'max:1000'],
            'search_keywords' => ['nullable', 'string', 'max:1000'],
            'lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
