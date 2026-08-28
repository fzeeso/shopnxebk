<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use Illuminate\Validation\ValidationException;

final class ProductDetailReferenceMap
{
    /** @var array<string, array<string, string>> */
    private array $references = [
        'options' => [],
        'option_values' => [],
        'variants' => [],
        'modifier_groups' => [],
    ];

    public function register(string $type, mixed $reference, string $publicId): void
    {
        if ($reference === null) {
            return;
        }

        $key = (string) $reference;
        if (! preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $key)) {
            throw ValidationException::withMessages([
                'references' => ["Reference [{$key}] is invalid."],
            ]);
        }
        if (isset($this->references[$type][$key])) {
            throw ValidationException::withMessages([
                'references' => ["Reference [@{$key}] is duplicated in section [{$type}]."],
            ]);
        }

        $this->references[$type][$key] = $publicId;
    }

    public function resolve(string $type, string $value): string
    {
        if (! str_starts_with($value, '@')) {
            return $value;
        }

        $key = substr($value, 1);
        if (! isset($this->references[$type][$key])) {
            throw ValidationException::withMessages([
                'references' => ["Reference [{$value}] has not been created in section [{$type}]."],
            ]);
        }

        return $this->references[$type][$key];
    }

    public function nullable(string $type, mixed $value): ?string
    {
        return $value === null ? null : $this->resolve($type, (string) $value);
    }

    /** @return array<string, array<string, string>> */
    public function all(): array
    {
        return array_filter($this->references);
    }
}
