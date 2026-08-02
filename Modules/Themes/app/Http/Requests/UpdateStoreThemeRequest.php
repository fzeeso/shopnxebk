<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateStoreThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'settings_data' => ['sometimes', 'array'],
            'template_data' => ['sometimes', 'array'],
            'custom_css' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'customization_revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
