<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:120'],
            'tenant_id' => ['nullable', 'uuid'],
            'abilities' => ['sometimes', 'array', 'max:20'],
            'abilities.*' => ['string', 'in:account:read,tenant:access,files:write,exports:write'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
