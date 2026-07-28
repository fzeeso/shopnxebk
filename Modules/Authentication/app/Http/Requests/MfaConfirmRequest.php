<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Requests;

final class MfaConfirmRequest extends MfaPasswordRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => preg_replace('/\s+/', '', (string) $this->input('code')),
            ]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);
    }
}
