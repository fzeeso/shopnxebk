<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Authentication\Models\User;

final class UpdateLanguageRequest extends FormRequest
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
        $values = [];

        if ($this->exists('name')) {
            $values['name'] = trim((string) $this->input('name'));
        }
        if ($this->exists('native_name')) {
            $values['native_name'] = trim((string) $this->input('native_name'));
        }
        if ($this->exists('direction')) {
            $values['direction'] = Str::lower(trim((string) $this->input('direction')));
        }
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
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'native_name' => ['sometimes', 'required', 'string', 'max:100'],
            'direction' => ['sometimes', 'required', Rule::in(['ltr', 'rtl'])],
            'lang_icon' => ['sometimes', 'required', 'string', 'max:2048', 'regex:/^(?:\/|https?:\/\/)/i'],
            'lang_image' => ['sometimes', 'required', 'string', 'max:2048', 'regex:/^(?:\/|https?:\/\/)/i'],
            'is_active' => ['sometimes', 'boolean'],
            'locale' => ['prohibited'],
        ];
    }
}
