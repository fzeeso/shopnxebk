<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CustomObjectTypeWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'handle' => [$required, 'string', 'max:150', 'regex:/^[a-z][a-z0-9-]*$/'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'is_system' => ['sometimes', 'boolean'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'fields' => $this->isMethod('post') ? ['sometimes', 'array', 'list', 'max:100'] : ['prohibited'],
            'fields.*' => ['array'],
        ];
    }
}
