<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

final readonly class IdempotencyPolicy
{
    public function __construct(
        public bool $enabled,
        public IdempotencyMode $mode,
        public int $ttlSeconds,
    ) {}

    public static function forOperation(string $operation): self
    {
        $policyName = config("idempotency.operations.{$operation}");
        $policy = is_string($policyName)
            ? config("idempotency.{$policyName}", [])
            : config('idempotency.default_policy', []);
        if (! is_array($policy)) {
            $policy = [];
        }

        $mode = IdempotencyMode::tryFrom((string) ($policy['mode'] ?? 'supported'))
            ?? IdempotencyMode::Supported;

        return new self(
            enabled: (bool) config('idempotency.enabled', false),
            mode: $mode,
            ttlSeconds: max(60, (int) ($policy['ttl_seconds'] ?? 86400)),
        );
    }
}
