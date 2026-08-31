<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Customers\Enums\CustomerStatus;

final class CustomerWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'email' => [$required, 'email:rfc', 'max:320'],
            'customer_group_id' => ['sometimes', 'nullable', 'ulid'],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
            'registered_ip' => ['sometimes', 'nullable', 'ip'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'points_balance' => ['sometimes', 'integer', 'min:0'],
            'redeemed_points' => ['sometimes', 'integer', 'min:0'],
            'joined_at' => ['sometimes', 'date'],
            'password' => $creating
                ? ['sometimes', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]
                : ['prohibited'],
            'password_confirmation' => $creating
                ? ['exclude_without:password', 'required_with:password', 'string']
                : ['prohibited'],
            'legacy_id' => ['prohibited'],
        ];
    }
}
