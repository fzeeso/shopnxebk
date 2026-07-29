<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\BillingInterval;

final class UpsertPlanFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('addon_currency_code') && $this->input('addon_currency_code') !== null) {
            $this->merge([
                'addon_currency_code' => Str::upper(trim((string) $this->input('addon_currency_code'))),
            ]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'value' => ['sometimes', 'nullable'],
            'is_included' => ['sometimes', 'boolean'],
            'is_addon' => ['sometimes', 'boolean'],
            'addon_price_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'addon_currency_code' => ['sometimes', 'nullable', 'string', 'alpha:ascii', 'size:3', 'exists:currencies,code'],
            'addon_billing_interval' => ['sometimes', 'nullable', Rule::enum(BillingInterval::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
