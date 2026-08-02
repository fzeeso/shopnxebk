<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Themes\Enums\ThemeSourceType;
use Modules\Themes\Enums\ThemeStatus;

final class ListThemesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'source_type' => ['sometimes', 'nullable', Rule::enum(ThemeSourceType::class)],
            'status' => ['sometimes', 'nullable', Rule::enum(ThemeStatus::class)],
            'visibility' => ['sometimes', 'nullable', Rule::in(['public', 'private', 'unlisted'])],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 25);
    }
}
