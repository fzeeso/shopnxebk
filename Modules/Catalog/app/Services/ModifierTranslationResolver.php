<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

final class ModifierTranslationResolver
{
    /**
     * @param  iterable<array<string, mixed>|object>  $assignmentTranslations
     * @param  iterable<array<string, mixed>|object>  $modifierTranslations
     * @return array{name: string, description: ?string, placeholder: ?string, helpText: ?string, requiredMessage: ?string, validationMessage: ?string}
     */
    public function resolveModifier(
        iterable $assignmentTranslations,
        iterable $modifierTranslations,
        string $requestedLocale,
        string $defaultLocale,
        string $fallbackCode,
    ): array {
        $assignment = $this->index($assignmentTranslations);
        $library = $this->index($modifierTranslations);
        $requested = $this->locale($requestedLocale);
        $default = $this->locale($defaultLocale);
        $override = $assignment[$requested] ?? [];
        $requestedLibrary = $library[$requested] ?? [];
        $defaultLibrary = $library[$default] ?? [];

        return [
            'name' => (string) ($this->first($override, 'name_override', $requestedLibrary, 'name', $defaultLibrary, 'name') ?? $this->fallback($fallbackCode)),
            'description' => $this->first($override, 'description_override', $requestedLibrary, 'description', $defaultLibrary, 'description'),
            'placeholder' => $this->first($override, 'placeholder_override', $requestedLibrary, 'placeholder', $defaultLibrary, 'placeholder'),
            'helpText' => $this->first($override, 'help_text_override', $requestedLibrary, 'help_text', $defaultLibrary, 'help_text'),
            'requiredMessage' => $this->first([], '', $requestedLibrary, 'required_message', $defaultLibrary, 'required_message'),
            'validationMessage' => $this->first([], '', $requestedLibrary, 'validation_message', $defaultLibrary, 'validation_message'),
        ];
    }

    /** @param iterable<array<string, mixed>|object> $translations */
    public function resolveValueName(iterable $translations, string $requestedLocale, string $defaultLocale, string $fallbackCode): string
    {
        $indexed = $this->index($translations);

        return (string) (($indexed[$this->locale($requestedLocale)]['name'] ?? null)
            ?: ($indexed[$this->locale($defaultLocale)]['name'] ?? null)
            ?: $fallbackCode);
    }

    /** @param iterable<array<string, mixed>|object> $rows @return array<string, array<string, mixed>> */
    private function index(iterable $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $values = is_array($row)
                ? $row
                : (method_exists($row, 'toArray') ? $row->toArray() : get_object_vars($row));
            $locale = $this->locale((string) ($values['locale'] ?? ''));
            if ($locale !== '') {
                $indexed[$locale] = $values;
            }
        }

        return $indexed;
    }

    /** @param array<string, mixed> $first @param array<string, mixed> $second @param array<string, mixed> $third */
    private function first(array $first, string $firstKey, array $second, string $secondKey, array $third, string $thirdKey): ?string
    {
        foreach ([[$first, $firstKey], [$second, $secondKey], [$third, $thirdKey]] as [$row, $key]) {
            $value = $key === '' ? null : ($row[$key] ?? null);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function locale(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }

    private function fallback(string $code): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $code));
    }
}
