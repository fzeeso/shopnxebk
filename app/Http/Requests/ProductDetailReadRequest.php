<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductDetailReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'section_limit' => ['sometimes', 'integer', 'min:1', 'max:250'],
            'reference_limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'with_reference_data' => ['sometimes', 'boolean'],
        ];
    }

    public function sectionLimit(): int
    {
        return (int) $this->validated('section_limit', 100);
    }

    public function referenceLimit(): int
    {
        return (int) $this->validated('reference_limit', 250);
    }

    public function withReferenceData(): bool
    {
        return $this->boolean('with_reference_data', true);
    }
}
