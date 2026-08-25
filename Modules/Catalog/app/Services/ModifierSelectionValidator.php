<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Carbon\CarbonImmutable;

final class ModifierSelectionValidator
{
    private const VALUE_TYPES = ['select', 'radio', 'buttons', 'swatch', 'checkbox', 'checkbox_group', 'toggle'];

    /**
     * @param  array<string, mixed>  $configuration
     * @param  list<array<string, mixed>>  $selections
     * @return array<string, list<string>>
     */
    public function validate(array $configuration, array $selections): array
    {
        $errors = [];
        $required = (bool) ($configuration['required'] ?? false);
        $min = $configuration['minSelections'] ?? ($required ? 1 : 0);
        $max = $configuration['maxSelections'] ?? null;
        $type = (string) ($configuration['type'] ?? '');
        $count = count($selections);

        if ($required && $this->isEmpty($selections)) {
            $errors['selections'][] = (string) ($configuration['requiredMessage'] ?? 'This modifier is required.');
        }
        if ($count < (int) $min) {
            $errors['selections'][] = "Select at least {$min} option(s).";
        }
        if ($max !== null && $count > (int) $max) {
            $errors['selections'][] = "Select no more than {$max} option(s).";
        }
        if (! (bool) ($configuration['supportsMultiple'] ?? false) && $count > 1) {
            $errors['selections'][] = 'This modifier accepts only one selection.';
        }

        if (in_array($type, self::VALUE_TYPES, true)) {
            $allowed = array_fill_keys(array_map(static fn (array $value): string => (string) $value['id'], $configuration['values'] ?? []), true);
            $selectedIds = array_map(static fn (array $selection): string => (string) ($selection['value_id'] ?? ''), $selections);
            if (count($selectedIds) !== count(array_unique($selectedIds))) {
                $errors['selections'][] = 'The same modifier value cannot be selected more than once.';
            }
            foreach ($selections as $index => $selection) {
                $valueId = (string) ($selection['value_id'] ?? '');
                if ($valueId === '' || ! isset($allowed[$valueId])) {
                    $errors["selections.{$index}.value_id"][] = 'The selected modifier value is not available.';
                }
            }
        } else {
            foreach ($selections as $index => $selection) {
                if (($selection['value_id'] ?? null) !== null) {
                    $errors["selections.{$index}.value_id"][] = 'This modifier accepts input rather than a catalogue value.';
                }
                $this->validateInput($type, $selection['input_value'] ?? null, $configuration['validationRules'] ?? [], $index, $errors);
            }
        }

        return $errors;
    }

    /** @param list<array<string, mixed>> $selections */
    private function isEmpty(array $selections): bool
    {
        if ($selections === []) {
            return true;
        }

        return ! collect($selections)->contains(static fn (array $selection): bool => ($selection['value_id'] ?? null) !== null
            || array_filter((array) ($selection['input_value'] ?? []), static fn (mixed $value): bool => $value !== null && $value !== '') !== []);
    }

    /** @param mixed $input @param list<array<string, mixed>> $rules @param array<string, list<string>> $errors */
    private function validateInput(string $type, mixed $input, array $rules, int $index, array &$errors): void
    {
        $input = is_array($input) ? $input : [];
        $key = match ($type) {
            'text', 'textarea' => 'text',
            'number' => 'number',
            'date' => 'date',
            'datetime' => 'datetime',
            'file', 'image_upload' => 'asset_ids',
            default => 'value',
        };
        $value = $input[$key] ?? null;
        $path = "selections.{$index}.input_value.{$key}";

        if (in_array($type, ['text', 'textarea'], true) && $value !== null && ! is_string($value)) {
            $errors[$path][] = 'The text input must be a string.';
        } elseif ($type === 'number' && $value !== null && ! is_numeric($value)) {
            $errors[$path][] = 'The number input must be numeric.';
        } elseif (in_array($type, ['date', 'datetime'], true) && $value !== null) {
            try {
                CarbonImmutable::parse((string) $value);
            } catch (\Throwable) {
                $errors[$path][] = 'The date input is invalid.';

                return;
            }
        } elseif (in_array($type, ['file', 'image_upload'], true)) {
            if (! is_array($value)) {
                $errors[$path][] = 'The file input must contain asset IDs.';
            } else {
                foreach ($value as $assetId) {
                    if (! is_string($assetId) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $assetId) !== 1) {
                        $errors[$path][] = 'Every asset ID must be a ULID.';
                        break;
                    }
                }
            }
        }

        foreach ($rules as $rule) {
            $ruleType = (string) ($rule['type'] ?? $rule['rule_type'] ?? '');
            $ruleValue = $rule['value'] ?? $rule['rule_value'] ?? null;
            $message = (string) ($rule['message'] ?? 'The modifier input is invalid.');
            $invalid = match ($ruleType) {
                'min_length' => is_string($value) && mb_strlen($value) < (int) $this->scalar($ruleValue),
                'max_length' => is_string($value) && mb_strlen($value) > (int) $this->scalar($ruleValue),
                'min_number' => is_numeric($value) && (float) $value < (float) $this->scalar($ruleValue),
                'max_number' => is_numeric($value) && (float) $value > (float) $this->scalar($ruleValue),
                'regex' => is_string($value) && @preg_match((string) $this->scalar($ruleValue), $value) !== 1,
                'max_files' => is_array($value) && count($value) > (int) $this->scalar($ruleValue),
                'min_date' => is_string($value) && $this->outsideDateRange($value, (string) $this->scalar($ruleValue), true),
                'max_date' => is_string($value) && $this->outsideDateRange($value, (string) $this->scalar($ruleValue), false),
                default => false,
            };
            if ($invalid) {
                $errors[$path][] = $message;
            }
        }
    }

    private function scalar(mixed $value): mixed
    {
        return is_array($value) ? ($value['value'] ?? reset($value)) : $value;
    }

    private function outsideDateRange(string $value, string $boundary, bool $minimum): bool
    {
        try {
            $date = CarbonImmutable::parse($value);
            $limit = CarbonImmutable::parse($boundary);

            return $minimum ? $date->lessThan($limit) : $date->greaterThan($limit);
        } catch (\Throwable) {
            return false;
        }
    }
}
