<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStoreLanguagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $languageIds = array_values(array_filter(
            (array) $this->input('language_ids', []),
            fn (mixed $value): bool => is_string($value),
        ));

        return [
            'language_ids' => ['required', 'array', 'min:1'],
            'language_ids.*' => [
                'required',
                'string',
                'ulid',
                'distinct:strict',
                Rule::exists('languages', 'public_id')->where('is_active', true),
            ],
            'default_language_id' => [
                'required',
                'string',
                'ulid',
                Rule::in($languageIds),
                Rule::exists('languages', 'public_id')->where('is_active', true),
            ],
        ];
    }
}
