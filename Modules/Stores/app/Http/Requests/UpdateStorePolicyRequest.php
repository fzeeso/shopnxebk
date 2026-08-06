<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateStorePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }
}
