<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GenerateMediaAiImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:3', 'max:4000'],
            'image_type' => ['sometimes', 'string', 'max:100'],
            'aspect_ratio' => ['sometimes', Rule::in(['1:1', '4:5', '16:9'])],
            'style' => ['sometimes', 'string', 'max:100'],
            'image_count' => ['sometimes', 'integer', 'min:1', 'max:4'],
            'quality' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
        ];
    }
}
