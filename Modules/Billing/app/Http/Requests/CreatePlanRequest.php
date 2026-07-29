<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;

final class CreatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::lower(trim((string) $this->input('slug'))),
            'currency_code' => Str::upper(trim((string) $this->input('currency_code', 'USD'))),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash:ascii', 'min:2', 'max:80', 'unique:plans,slug'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'best_for' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency_code' => ['required', 'string', 'alpha:ascii', 'size:3', 'exists:currencies,code'],
            'billing_interval' => ['sometimes', 'nullable', Rule::enum(BillingInterval::class)],
            'is_custom_pricing' => ['required', 'boolean'],
            'status' => ['sometimes', Rule::enum(PlanStatus::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
