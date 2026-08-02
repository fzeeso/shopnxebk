<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InstallStoreThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme_id' => ['required', 'string', 'exists:themes,public_id'],
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'as_trial' => ['sometimes', 'boolean'],
        ];
    }
}
