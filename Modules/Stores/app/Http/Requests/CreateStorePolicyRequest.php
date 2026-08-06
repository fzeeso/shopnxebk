<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateStorePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'policy_type_id' => ['required', 'string', 'ulid', Rule::exists('policy_types', 'public_id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }
}
