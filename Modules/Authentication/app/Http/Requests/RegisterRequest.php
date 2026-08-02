<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [
            'email' => Str::lower(trim((string) $this->input('email'))),
            'store_slug' => Str::lower(trim((string) $this->input('store_slug'))),
        ];

        if ($this->exists('theme_template_key')) {
            $values['theme_template_key'] = Str::lower(trim((string) $this->input('theme_template_key')));
        }

        if ($this->exists('store_country_code') && $this->input('store_country_code') !== null) {
            $values['store_country_code'] = Str::upper(trim((string) $this->input('store_country_code')));
        }

        $this->merge($values);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'store_name' => ['required', 'string', 'max:120'],
            'store_slug' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:stores,slug'],
            'theme_template_key' => ['sometimes', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'store_country_code' => ['sometimes', 'nullable', 'string', 'alpha:ascii', 'size:2'],
            'store_state' => ['sometimes', 'nullable', 'string', 'max:120'],
            'store_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'store_zip' => ['sometimes', 'nullable', 'string', 'max:32'],
            'store_address_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'store_address_2' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
