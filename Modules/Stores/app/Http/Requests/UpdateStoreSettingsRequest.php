<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateStoreSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        if ($this->exists('currency_code') && $this->input('currency_code') !== null) {
            $values['currency_code'] = Str::upper(trim((string) $this->input('currency_code')));
        }
        if ($this->exists('language_code') && $this->input('language_code') !== null) {
            $locale = str_replace('-', '_', trim((string) $this->input('language_code')));
            $parts = explode('_', $locale, 2);
            $values['language_code'] = Str::lower($parts[0]);
            if (isset($parts[1]) && $parts[1] !== '') {
                $values['language_code'] .= '_'.Str::upper($parts[1]);
            }
        }
        foreach (['country_code', 'store_country_code'] as $field) {
            if ($this->exists($field) && $this->input($field) !== null) {
                $values[$field] = Str::upper(trim((string) $this->input($field)));
            }
        }

        $this->merge($values);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'currency_code' => ['sometimes', 'string', 'alpha:ascii', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
            'language_code' => ['sometimes', 'string', 'max:10', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/', Rule::exists('languages', 'locale')->where('is_active', true)],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'country_code' => ['sometimes', 'nullable', 'string', 'alpha:ascii', 'size:2'],
            'contact_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'store_country_code' => ['sometimes', 'nullable', 'string', 'alpha:ascii', 'size:2'],
            'store_state' => ['sometimes', 'nullable', 'string', 'max:120'],
            'store_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'store_zip' => ['sometimes', 'nullable', 'string', 'max:32'],
            'store_address_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'store_address_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferences' => ['sometimes', 'array:order_prefix,date_format,time_format,weight_unit,dimension_unit,inventory_tracking,guest_checkout,tax_inclusive_pricing,low_stock_threshold,support_email'],
            'preferences.order_prefix' => ['sometimes', 'string', 'max:12', 'regex:/^[A-Za-z0-9_-]+$/'],
            'preferences.date_format' => ['sometimes', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y'])],
            'preferences.time_format' => ['sometimes', Rule::in(['12h', '24h'])],
            'preferences.weight_unit' => ['sometimes', Rule::in(['kg', 'lb'])],
            'preferences.dimension_unit' => ['sometimes', Rule::in(['cm', 'in'])],
            'preferences.inventory_tracking' => ['sometimes', 'boolean'],
            'preferences.guest_checkout' => ['sometimes', 'boolean'],
            'preferences.tax_inclusive_pricing' => ['sometimes', 'boolean'],
            'preferences.low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'preferences.support_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'status' => ['prohibited'],
            'plan_id' => ['prohibited'],
            'subscription_id' => ['prohibited'],
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
