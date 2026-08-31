<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Http\Request;
use InvalidArgumentException;

final class IdempotencyKeyParser
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function isPresent(Request $request): bool
    {
        return $request->headers->all('Idempotency-Key') !== [];
    }

    public function parse(Request $request): ?string
    {
        $values = $request->headers->all('Idempotency-Key');
        if ($values === []) {
            return null;
        }

        if (count($values) !== 1) {
            throw new InvalidArgumentException('Exactly one Idempotency-Key header is allowed.');
        }

        $value = trim((string) $values[0]);
        if (str_starts_with($value, '"') || str_ends_with($value, '"')) {
            if (strlen($value) !== 38 || $value[0] !== '"' || $value[37] !== '"') {
                throw new InvalidArgumentException('Idempotency-Key structured string syntax is invalid.');
            }

            $value = substr($value, 1, -1);
        }

        if (preg_match(self::UUID_V4, $value) !== 1) {
            throw new InvalidArgumentException('Idempotency-Key must be a UUIDv4.');
        }

        return strtolower($value);
    }
}
