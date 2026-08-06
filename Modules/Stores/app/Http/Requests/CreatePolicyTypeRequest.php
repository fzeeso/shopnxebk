<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePolicyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('policy_types', 'code')],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
