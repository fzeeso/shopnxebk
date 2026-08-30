<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CollectionWriteRequest extends FormRequest
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
            'parent_id' => ['sometimes', 'nullable', 'ulid'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:/^(?:\/|https?:\/\/)/i'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'collection_type' => ['sometimes', 'in:manual,rule_based,ai_generated'],
            'rules_match_type' => ['sometimes', 'in:all,any'],
            'ai_prompt' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'ai_model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:35'],
            'translations.*.title' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
            'translations.*.seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.*.seo_description' => ['sometimes', 'nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'rules' => ['sometimes', 'array', 'list', 'max:100'],
            'rules.*.field' => ['required', 'string', 'max:50'],
            'rules.*.operator' => ['required', 'string', 'max:20'],
            'rules.*.value' => ['required', 'string', 'max:255'],
            'rules.*.position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'products' => ['sometimes', 'array', 'list', 'max:1000'],
            'products.*.product_id' => ['required', 'ulid', 'distinct'],
            'products.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'products.*.is_pinned' => ['sometimes', 'boolean'],
        ];
    }
}
