<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Http\Request;
use JsonException;

final class IdempotencyFingerprinter
{
    /** @throws JsonException */
    public function fingerprint(Request $request, string $operation): string
    {
        $query = $request->query();
        $this->sortRecursively($query);

        $routeParameters = [];
        foreach ($request->route()->parameters() as $name => $value) {
            $routeParameters[(string) $name] = $this->routeValue($value);
        }
        ksort($routeParameters);

        return hash('sha256', json_encode([
            'version' => (int) config('idempotency.fingerprint_version', 1),
            'method' => strtoupper($request->getMethod()),
            'operation' => $operation,
            'route_parameters' => $routeParameters,
            'query' => $query,
            'content_type' => strtolower(trim((string) $request->headers->get('Content-Type'))),
            'body_sha256' => hash('sha256', (string) $request->getContent()),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function routeValue(mixed $value): mixed
    {
        if ($value instanceof UrlRoutable) {
            return $value->getRouteKey();
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return get_debug_type($value);
    }

    /** @param array<array-key, mixed> $values */
    private function sortRecursively(array &$values): void
    {
        if (! array_is_list($values)) {
            ksort($values);
        }

        foreach ($values as &$value) {
            if (is_array($value)) {
                $this->sortRecursively($value);
            }
        }
        unset($value);
    }
}
