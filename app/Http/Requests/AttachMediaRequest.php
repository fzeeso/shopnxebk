<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AttachMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'media_id' => ['required', 'ulid'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
