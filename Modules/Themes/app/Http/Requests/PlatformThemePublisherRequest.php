<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Themes\Models\ThemePublisher;

final class PlatformThemePublisherRequest extends FormRequest
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
        $publisher = $this->route('themePublisher');
        $ignoreId = $publisher instanceof ThemePublisher ? $publisher->getKey() : null;

        return [
            'owner_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,public_id'],
            'publisher_type' => [$required, Rule::in(['platform', 'third_party'])],
            'display_name' => [$required, 'string', 'max:160'],
            'slug' => [$required, 'alpha_dash:ascii', 'max:100', Rule::unique('theme_publishers', 'slug')->ignore($ignoreId)],
            'status' => [$required, Rule::in(['pending', 'active', 'suspended', 'rejected', 'closed'])],
            'support_email' => [$required, 'email:rfc', 'max:254'],
            'support_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'website_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'payout_account_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_commission_bps' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
            'verified_at' => ['sometimes', 'nullable', 'date'],
            'terms_accepted_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
