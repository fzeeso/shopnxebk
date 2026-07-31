<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Stores\Enums\BusinessType;
use Modules\Stores\Enums\StoreStatus;

abstract class PlatformStoreWriteRequest extends PlatformStoreRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['name', 'legal_name', 'email', 'phone', 'slug', 'primary_domain', 'industry'] as $field) {
            if ($this->exists($field) && $this->input($field) !== null) {
                $values[$field] = trim((string) $this->input($field));
            }
        }

        foreach (['email', 'slug', 'primary_domain'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = Str::lower($values[$field]);
            }
        }

        foreach (['currency_code', 'country_code'] as $field) {
            if ($this->exists($field) && $this->input($field) !== null) {
                $values[$field] = Str::upper(trim((string) $this->input($field)));
            }
        }

        if ($this->exists('language_code') && $this->input('language_code') !== null) {
            $locale = str_replace('-', '_', trim((string) $this->input('language_code')));
            $parts = explode('_', $locale, 2);
            $values['language_code'] = Str::lower($parts[0]);
            if (isset($parts[1]) && $parts[1] !== '') {
                $values['language_code'] .= '_'.Str::upper($parts[1]);
            }
        }

        $this->merge($values);
    }

    /** @return array<string, list<mixed>> */
    protected function storeRules(?int $storeKey, bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $slugUnique = Rule::unique('stores', 'slug');
        $domainUnique = Rule::unique('stores', 'primary_domain');
        if ($storeKey !== null) {
            $slugUnique->ignore($storeKey);
            $domainUnique->ignore($storeKey);
        }

        return [
            'name' => [$required, 'string', 'max:120'],
            'slug' => [$required, 'alpha_dash:ascii', 'min:3', 'max:80', $slugUnique],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'primary_domain' => ['sometimes', 'nullable', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $domainUnique],
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'favicon' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'cover_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:120'],
            'business_type' => ['sometimes', 'nullable', Rule::enum(BusinessType::class)],
            'status' => ['sometimes', Rule::enum(StoreStatus::class)],
            'currency_code' => ['sometimes', 'string', 'alpha:ascii', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
            'language_code' => ['sometimes', 'string', 'max:10', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/', Rule::exists('languages', 'locale')->where('is_active', true)],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'country_code' => ['sometimes', 'nullable', 'string', 'alpha:ascii', 'size:2'],
            'is_verified' => ['sometimes', 'boolean'],
            'is_ai_enabled' => ['sometimes', 'boolean'],
            'is_pos_enabled' => ['sometimes', 'boolean'],
            'is_b2b_enabled' => ['sometimes', 'boolean'],
            'is_marketplace_enabled' => ['sometimes', 'boolean'],
            'launched_at' => ['sometimes', 'nullable', 'date'],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
            'plan_id' => ['prohibited'],
            'subscription_id' => ['prohibited'],
            'settings' => ['prohibited'],
            'metadata' => ['prohibited'],
            'preferences' => ['prohibited'],
            'owner' => ['prohibited'],
            'roles' => ['prohibited'],
        ];
    }
}
