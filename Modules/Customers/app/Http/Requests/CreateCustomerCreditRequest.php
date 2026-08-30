<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerCreditType;

final class CreateCustomerCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'not_in:0', 'decimal:0,4'],
            'type' => ['required', Rule::enum(CustomerCreditType::class)],
            'external_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:200'],
            'occurred_at' => ['sometimes', 'date'],
            'legacy_id' => ['prohibited'],
            'legacy_user_id' => ['prohibited'],
        ];
    }
}
