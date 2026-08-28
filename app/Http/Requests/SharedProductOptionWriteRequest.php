<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Services\SharedProductOptionService;

final class SharedProductOptionWriteRequest extends FormRequest
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
            'name' => [$required, 'string', 'max:100', 'not_regex:/^\s*$/u'],
            'type' => [$required, 'string', Rule::in(SharedProductOptionService::TYPES)],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => [$required, 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35', 'distinct'],
            'translations.*.display_name' => ['required', 'string', 'max:100', 'not_regex:/^\s*$/u'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
            'values' => [$required, 'array', 'list', 'min:1', 'max:100'],
            'values.*.id' => ['sometimes', 'nullable', 'ulid'],
            'values.*.position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'values.*.is_default' => ['sometimes', 'boolean'],
            'values.*.translations' => [
                'required',
                'array',
                'list',
                'min:1',
                static function (string $attribute, mixed $translations, Closure $fail): void {
                    if (! is_array($translations)) {
                        return;
                    }

                    $locales = [];

                    foreach ($translations as $translation) {
                        if (! is_array($translation) || ! is_string($translation['locale'] ?? null)) {
                            continue;
                        }

                        if (in_array($translation['locale'], $locales, true)) {
                            $fail('Each option value translation locale must be distinct.');

                            return;
                        }

                        $locales[] = $translation['locale'];
                    }
                },
            ],
            'values.*.translations.*.locale' => ['required', 'string', 'max:35'],
            'values.*.translations.*.display_label' => ['required', 'string', 'max:100', 'not_regex:/^\s*$/u'],
            'values.*.translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
