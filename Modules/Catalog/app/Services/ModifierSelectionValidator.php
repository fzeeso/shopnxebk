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
        $validationMessage = $this->message($configuration, 'The modifier selection is invalid.');

        if ($required && $this->isEmpty($selections)) {
            $errors['selections'][] = $this->message($configuration, 'This modifier is required.', 'requiredMessage');
        }
        if ($count < (int) $min) {
            $errors['selections'][] = $this->message($configuration, "Select at least {$min} option(s).");
        }
        if ($max !== null && $count > (int) $max) {
            $errors['selections'][] = $this->message($configuration, "Select no more than {$max} option(s).");
        }
        if (! (bool) ($configuration['supportsMultiple'] ?? false) && $count > 1) {
            $errors['selections'][] = $validationMessage;
        }

        if (in_array($type, self::VALUE_TYPES, true)) {
            $allowed = array_fill_keys(array_map(static fn (array $value): string => (string) $value['id'], $configuration['values'] ?? []), true);
            $selectedIds = array_map(static fn (array $selection): string => (string) ($selection['value_id'] ?? ''), $selections);
            if (count($selectedIds) !== count(array_unique($selectedIds))) {
                $errors['selections'][] = $validationMessage;
            }
            foreach ($selections as $index => $selection) {
                $valueId = (string) ($selection['value_id'] ?? '');
                if ($valueId === '' || ! isset($allowed[$valueId])) {
                    $errors["selections.{$index}.value_id"][] = $validationMessage;
                }
            }
        } else {
            foreach ($selections as $index => $selection) {
                if (($selection['value_id'] ?? null) !== null) {
                    $errors["selections.{$index}.value_id"][] = $validationMessage;
                }
                $this->validateInput($type, $selection['input_value'] ?? null, $configuration['validationRules'] ?? [], $index, $errors, $validationMessage);
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
    private function validateInput(string $type, mixed $input, array $rules, int $index, array &$errors, string $validationMessage): void
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
            $errors[$path][] = $validationMessage;
        } elseif ($type === 'number' && $value !== null && ! is_numeric($value)) {
            $errors[$path][] = $validationMessage;
        } elseif (in_array($type, ['date', 'datetime'], true) && $value !== null) {
            try {
                CarbonImmutable::parse((string) $value);
            } catch (\Throwable) {
                $errors[$path][] = $validationMessage;

                return;
            }
        } elseif (in_array($type, ['file', 'image_upload'], true)) {
            if (! is_array($value)) {
                $errors[$path][] = $validationMessage;
            } else {
                foreach ($value as $assetId) {
                    if (! is_string($assetId) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $assetId) !== 1) {
                        $errors[$path][] = $validationMessage;
                        break;
                    }
                }
            }
        }

        foreach ($rules as $rule) {
            $ruleType = (string) ($rule['type'] ?? $rule['rule_type'] ?? '');
            $ruleValue = $rule['value'] ?? $rule['rule_value'] ?? null;
            $message = trim((string) ($rule['message'] ?? '')) ?: $validationMessage;
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

    /** @param array<string, mixed> $configuration */
    private function message(array $configuration, string $fallback, string $key = 'validationMessage'): string
    {
        $message = trim((string) ($configuration[$key] ?? ''));

        return $message !== '' ? $message : $fallback;
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
