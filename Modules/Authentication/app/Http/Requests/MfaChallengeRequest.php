<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MfaChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'challenge_token' => trim((string) $this->input('challenge_token')),
        ];

        if ($this->filled('code')) {
            $normalized['code'] = preg_replace('/\s+/', '', (string) $this->input('code'));
        }

        if ($this->filled('recovery_code')) {
            $normalized['recovery_code'] = trim((string) $this->input('recovery_code'));
        }

        $this->merge($normalized);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'min:40', 'max:255'],
            'code' => ['nullable', 'required_without:recovery_code', 'prohibits:recovery_code', 'string', 'regex:/^\d{6}$/'],
            'recovery_code' => ['nullable', 'required_without:code', 'prohibits:code', 'string', 'max:100'],
        ];
    }
}
