<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Catalog\Services\ProductDetailSectionRegistry;

final class ProductDetailReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $sectionRegistry = app(ProductDetailSectionRegistry::class);
        $availableSections = [
            'product',
            ...ProductDetailSectionRegistry::BUILT_IN_SECTIONS,
            ...$sectionRegistry->keys(),
        ];

        return [
            'section_limit' => ['sometimes', 'integer', 'min:1', 'max:250'],
            'reference_limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'with_reference_data' => ['sometimes', 'boolean'],
            'sections' => [
                'sometimes',
                'bail',
                'string',
                'max:2000',
                'regex:/^[a-z][a-z0-9_]*(?:,[a-z][a-z0-9_]*)*$/',
                static function (string $attribute, mixed $value, Closure $fail) use ($availableSections): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $selected = explode(',', $value);
                    if (count($selected) !== count(array_unique($selected))) {
                        $fail('The selected Product Detail sections must be distinct.');

                        return;
                    }
                    foreach ($selected as $section) {
                        if (! in_array($section, $availableSections, true)) {
                            $fail("The selected Product Detail section [{$section}] is not available.");

                            return;
                        }
                    }
                },
            ],
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

    /** @return list<string>|null */
    public function selectedSections(): ?array
    {
        $sections = $this->validated('sections');

        return is_string($sections) ? explode(',', $sections) : null;
    }
}
