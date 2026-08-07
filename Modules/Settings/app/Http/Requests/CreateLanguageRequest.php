<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Authentication\Models\User;

final class CreateLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User || ! $user->isPlatformUser()) {
            return false;
        }

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            return $user->can('manage platform settings');
        } finally {
            setPermissionsTeamId($previousStoreId);
        }
    }

    protected function prepareForValidation(): void
    {
        $locale = str_replace('-', '_', trim((string) $this->input('locale')));
        $parts = explode('_', $locale, 2);
        $normalizedLocale = Str::lower($parts[0]);

        if (isset($parts[1]) && $parts[1] !== '') {
            $normalizedLocale .= '_'.Str::upper($parts[1]);
        }

        $values = [
            'name' => trim((string) $this->input('name')),
            'native_name' => trim((string) $this->input('native_name')),
            'locale' => $normalizedLocale,
            'direction' => Str::lower(trim((string) $this->input('direction', 'ltr'))),
        ];

        if ($this->exists('lang_icon')) {
            $values['lang_icon'] = trim((string) $this->input('lang_icon'));
        }
        if ($this->exists('lang_image')) {
            $values['lang_image'] = trim((string) $this->input('lang_image'));
        }

        $this->merge($values);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'native_name' => ['required', 'string', 'max:100'],
            'locale' => [
                'required',
                'string',
                'max:10',
                'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/',
                Rule::unique('languages', 'locale'),
            ],
            'lang_icon' => ['sometimes', 'required', 'string', 'max:2048', 'regex:/^(?:\/|https?:\/\/)/i'],
            'lang_image' => ['sometimes', 'required', 'string', 'max:2048', 'regex:/^(?:\/|https?:\/\/)/i'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
