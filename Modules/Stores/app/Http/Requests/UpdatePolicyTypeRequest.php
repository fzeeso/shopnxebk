<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Stores\Models\PolicyType;

final class UpdatePolicyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $policyType = $this->route('policyType');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('policy_types', 'code')->ignore($policyType instanceof PolicyType ? $policyType->getKey() : null),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
