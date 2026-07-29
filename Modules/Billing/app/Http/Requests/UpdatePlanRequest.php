<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Models\Plan;

final class UpdatePlanRequest extends FormRequest
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
        if ($this->exists('currency_code')) {
            $values['currency_code'] = Str::upper(trim((string) $this->input('currency_code')));
        }
        $this->merge($values);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $publicId = (string) $this->route('plan');
        $planId = Plan::query()->where('public_id', $publicId)->value('id');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'alpha_dash:ascii', 'min:2', 'max:80', Rule::unique('plans', 'slug')->ignore($planId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'best_for' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency_code' => ['sometimes', 'string', 'alpha:ascii', 'size:3', 'exists:currencies,code'],
            'billing_interval' => ['sometimes', 'nullable', Rule::enum(BillingInterval::class)],
            'is_custom_pricing' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(PlanStatus::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
