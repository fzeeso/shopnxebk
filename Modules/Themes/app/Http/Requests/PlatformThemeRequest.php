<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Themes\Enums\ThemeSourceType;
use Modules\Themes\Enums\ThemeStatus;
use Modules\Themes\Models\Theme;

final class PlatformThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        if ($this->exists('slug')) {
            $values['slug'] = Str::lower(trim((string) $this->input('slug')));
        }
        if ($this->exists('price_currency')) {
            $values['price_currency'] = Str::upper(trim((string) $this->input('price_currency')));
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $theme = $this->route('theme');
        $ignoreId = $theme instanceof Theme ? $theme->getKey() : null;

        return [
            'publisher_id' => [$required, 'nullable', 'string', 'exists:theme_publishers,public_id'],
            'owner_store_id' => [$required, 'nullable', 'string', 'exists:stores,public_id'],
            'name' => [$required, 'string', 'max:160'],
            'slug' => [$required, 'alpha_dash:ascii', 'max:120', Rule::unique('themes', 'slug')->ignore($ignoreId)],
            'summary' => [$required, 'string', 'max:320'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'source_type' => [$required, Rule::enum(ThemeSourceType::class)],
            'visibility' => [$required, Rule::in(['public', 'private', 'unlisted'])],
            'commercial_type' => [$required, Rule::in(['free', 'paid', 'private'])],
            'status' => ['sometimes', Rule::enum(ThemeStatus::class)],
            'price_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'price_currency' => ['sometimes', 'nullable', 'string', 'size:3', 'exists:currencies,code'],
            'support_email' => ['sometimes', 'nullable', 'email:rfc', 'max:254'],
            'support_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'documentation_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'demo_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'listing_metadata' => ['sometimes', 'array'],
            'is_featured' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'array', 'max:30'],
            'category_ids.*' => ['string', 'distinct', 'exists:theme_categories,public_id'],
            'primary_category_id' => ['sometimes', 'nullable', 'string', 'exists:theme_categories,public_id'],
        ];
    }
}
