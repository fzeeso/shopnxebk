<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerGroupDiscountAppliesTo;
use Modules\Customers\Enums\CustomerGroupDiscountTarget;

final class CustomerGroupDiscountWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::enum(CustomerGroupDiscountTarget::class)],
            'target_id' => ['required', 'ulid'],
            'discount_percentage' => ['required', 'numeric', 'decimal:0,4', 'between:0,100'],
            'applies_to' => ['required', Rule::enum(CustomerGroupDiscountAppliesTo::class)],
            'discount_method' => ['required', 'string', 'max:100'],
            'legacy_id' => ['prohibited'],
        ];
    }
}
