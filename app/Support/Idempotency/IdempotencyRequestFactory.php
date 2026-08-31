<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Http\Request;
use JsonException;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use RuntimeException;

final readonly class IdempotencyRequestFactory
{
    public function __construct(
        private IdempotencyFingerprinter $fingerprinter,
        private StoreContext $storeContext,
    ) {}

    /** @throws JsonException */
    public function make(
        Request $request,
        User $actor,
        string $operation,
        string $key,
        int $ttlSeconds,
    ): IdempotencyRequest {
        $secret = (string) config('idempotency.hmac_key');
        if ($secret === '') {
            throw new RuntimeException('Idempotency HMAC key is not configured.');
        }

        $storeId = $this->storeContext->id();
        $scope = json_encode([
            'version' => (int) config('idempotency.contract_version', 1),
            'actor_type' => $actor::class,
            'actor_id' => (int) $actor->getKey(),
            'store_id' => $storeId,
            'operation' => $operation,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $scopeHash = hash_hmac('sha256', $scope, $secret);
        $keyHash = hash_hmac('sha256', $key, $secret);
        $lockHash = hash('sha256', $scopeHash.':'.$keyHash);

        return new IdempotencyRequest(
            operation: $operation,
            scopeHash: $scopeHash,
            keyHash: $keyHash,
            fingerprint: $this->fingerprinter->fingerprint($request, $operation),
            actorId: (int) $actor->getKey(),
            storeId: $storeId,
            lockKeyHigh: $this->signedInt32(substr($lockHash, 0, 8)),
            lockKeyLow: $this->signedInt32(substr($lockHash, 8, 8)),
            ttlSeconds: $ttlSeconds,
        );
    }

    private function signedInt32(string $hex): int
    {
        $value = (int) hexdec($hex);

        return $value > 0x7FFFFFFF ? $value - 0x100000000 : $value;
    }
}
