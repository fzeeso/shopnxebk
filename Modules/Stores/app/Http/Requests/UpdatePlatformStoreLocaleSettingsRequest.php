<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdatePlatformStoreLocaleSettingsRequest extends PlatformStoreRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];

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
    public function rules(): array
    {
        return [
            'currency_code' => ['sometimes', 'string', 'alpha:ascii', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
            'language_code' => ['sometimes', 'string', 'max:10', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/', Rule::exists('languages', 'locale')->where('is_active', true)],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'country_code' => ['sometimes', 'nullable', 'string', 'alpha:ascii', 'size:2'],
            'date_format' => ['sometimes', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y'])],
            'time_format' => ['sometimes', Rule::in(['12h', '24h'])],
            'week_starts_on' => ['sometimes', Rule::in(['monday', 'sunday', 'saturday'])],
            'weight_unit' => ['sometimes', Rule::in(['kg', 'lb'])],
            'dimension_unit' => ['sometimes', Rule::in(['cm', 'in'])],
            'decimal_places' => ['sometimes', 'integer', 'min:0', 'max:4'],
            'decimal_separator' => ['sometimes', Rule::in(['dot', 'comma'])],
            'thousands_separator' => ['sometimes', Rule::in(['comma', 'dot', 'space', 'none'])],
        ];
    }
}
