<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IssueThemeLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'string', 'exists:stores,public_id'],
            'license_type' => ['required', Rule::in(['trial', 'free', 'paid', 'custom_owner', 'complimentary'])],
            'billing_order_item_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'purchased_by_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,public_id'],
            'trial_expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
