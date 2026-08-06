<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Requests;

use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends MfaPasswordRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.different' => 'The new password must be different from your current password.',
        ];
    }
}
