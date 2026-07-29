<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\FeatureValueType;

final class CreateFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['key' => Str::snake(trim((string) $this->input('key')))]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/', 'unique:features,key'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'value_type' => ['required', Rule::enum(FeatureValueType::class)],
            'unit' => ['sometimes', 'nullable', 'string', 'max:32'],
            'is_addon_eligible' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
