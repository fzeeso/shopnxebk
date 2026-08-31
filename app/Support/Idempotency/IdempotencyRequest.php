<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

final readonly class IdempotencyRequest
{
    public function __construct(
        public string $operation,
        public string $scopeHash,
        public string $keyHash,
        public string $fingerprint,
        public int $actorId,
        public ?int $storeId,
        public int $lockKeyHigh,
        public int $lockKeyLow,
        public int $ttlSeconds,
    ) {}
}
