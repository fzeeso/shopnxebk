<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MediaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::enum(MediaStatus::class)->except(MediaStatus::Deleted)],
            'mime_type' => ['sometimes', 'string', Rule::in(array_keys((array) config('media-management.allowed_mime_types')))],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
