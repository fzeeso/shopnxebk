<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewThemeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'changes_requested', 'rejected'])],
            'review_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'rejection_codes' => ['sometimes', 'array', 'max:100'],
            'rejection_codes.*' => ['string', 'max:120', 'distinct'],
        ];
    }
}
