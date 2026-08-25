<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MediaAiOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RunMediaAiOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::enum(MediaAiOperation::class)],
            'quality' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
        ];
    }
}
