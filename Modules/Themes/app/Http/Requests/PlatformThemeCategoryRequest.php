<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Themes\Models\ThemeCategory;

final class PlatformThemeCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('slug')) {
            $this->merge(['slug' => Str::lower(trim((string) $this->input('slug')))]);
        }
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $category = $this->route('themeCategory');
        $ignoreId = $category instanceof ThemeCategory ? $category->getKey() : null;

        return [
            'parent_id' => ['sometimes', 'nullable', 'string', 'exists:theme_categories,public_id'],
            'name' => [$required, 'string', 'max:120'],
            'slug' => [$required, 'alpha_dash:ascii', 'max:120', Rule::unique('theme_categories', 'slug')->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category_type' => [$required, Rule::in(['industry', 'style', 'feature', 'catalog_size'])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
