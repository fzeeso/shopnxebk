<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Stores\Enums\BusinessType;
use Modules\Stores\Models\Store;

final class UpdateStoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['slug', 'email', 'primary_domain'] as $field) {
            if ($this->exists($field) && $this->input($field) !== null) {
                $values[$field] = Str::lower(trim((string) $this->input($field)));
            }
        }

        $this->merge($values);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $store = $this->attributes->get('store');
        $storeKey = $store instanceof Store ? $store->getKey() : null;

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'alpha_dash:ascii', 'min:3', 'max:80', Rule::unique('stores', 'slug')->ignore($storeKey)],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'primary_domain' => ['sometimes', 'nullable', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', Rule::unique('stores', 'primary_domain')->ignore($storeKey)],
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'favicon' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'cover_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:120'],
            'business_type' => ['sometimes', 'nullable', Rule::enum(BusinessType::class)],
            'status' => ['prohibited'],
            'plan_id' => ['prohibited'],
            'subscription_id' => ['prohibited'],
            'currency_code' => ['prohibited'],
            'language_code' => ['prohibited'],
            'timezone' => ['prohibited'],
            'country_code' => ['prohibited'],
            'is_verified' => ['prohibited'],
            'is_ai_enabled' => ['prohibited'],
            'is_pos_enabled' => ['prohibited'],
            'is_b2b_enabled' => ['prohibited'],
            'is_marketplace_enabled' => ['prohibited'],
            'launched_at' => ['prohibited'],
            'trial_ends_at' => ['prohibited'],
            'settings' => ['prohibited'],
            'metadata' => ['prohibited'],
        ];
    }
}
