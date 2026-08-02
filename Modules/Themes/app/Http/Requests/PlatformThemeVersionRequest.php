<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PlatformThemeVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'regex:/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?$/', 'max:30'],
            'engine_version' => ['sometimes', 'string', 'max:30'],
            'minimum_platform_version' => ['sometimes', 'nullable', 'string', 'max:30'],
            'maximum_platform_version' => ['sometimes', 'nullable', 'string', 'max:30'],
            'source_archive_object_key' => ['required', 'string', 'max:2048'],
            'compiled_artifact_object_key' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'package_sha256' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'package_size_bytes' => ['required', 'integer', 'min:1', 'max:104857600'],
            'uncompressed_size_bytes' => ['required', 'integer', 'min:1', 'max:524288000'],
            'file_count' => ['required', 'integer', 'min:1', 'max:10000'],
            'manifest' => ['required', 'array'],
            'settings_schema' => ['sometimes', 'array'],
            'validation_report' => ['sometimes', 'array'],
            'release_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }
}
