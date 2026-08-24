<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('media-management.max_file_size_kb', 10240),
                'mimetypes:'.implode(',', array_keys((array) config('media-management.allowed_mime_types'))),
            ],
            'disk' => ['sometimes', 'string', Rule::in((array) config('media-management.allowed_disks'))],
            'visibility' => ['sometimes', Rule::in(['private', 'public'])],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
