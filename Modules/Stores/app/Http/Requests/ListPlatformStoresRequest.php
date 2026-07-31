<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Stores\Enums\BusinessType;
use Modules\Stores\Enums\StoreStatus;

final class ListPlatformStoresRequest extends PlatformStoreRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['search', 'status', 'business_type', 'sort', 'direction'] as $field) {
            if ($this->exists($field) && $this->input($field) !== null) {
                $values[$field] = Str::lower(trim((string) $this->input($field)));
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

        foreach (['is_verified', 'is_ai_enabled', 'is_pos_enabled', 'is_b2b_enabled', 'is_marketplace_enabled'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $boolean = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolean !== null) {
                $values[$field] = $boolean;
            }
        }

        $this->merge($values);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', Rule::enum(StoreStatus::class)],
            'business_type' => ['sometimes', Rule::enum(BusinessType::class)],
            'currency_code' => ['sometimes', 'string', 'alpha:ascii', 'size:3'],
            'language_code' => ['sometimes', 'string', 'max:10', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/'],
            'country_code' => ['sometimes', 'string', 'alpha:ascii', 'size:2'],
            'is_verified' => ['sometimes', 'boolean'],
            'is_ai_enabled' => ['sometimes', 'boolean'],
            'is_pos_enabled' => ['sometimes', 'boolean'],
            'is_b2b_enabled' => ['sometimes', 'boolean'],
            'is_marketplace_enabled' => ['sometimes', 'boolean'],
            'created_from' => ['sometimes', 'date_format:Y-m-d'],
            'created_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'sort' => ['sometimes', Rule::in(['name', 'slug', 'status', 'created_at', 'updated_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
