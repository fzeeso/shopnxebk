<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use JsonException;
use Throwable;

final readonly class ProductDetailReferenceCache
{
    public function __construct(private Repository $cache) {}

    /** @param Closure(): array<string, mixed> $resolver @return array<string, mixed> */
    public function remember(int $storeId, int $limit, Closure $resolver): array
    {
        if (! $this->enabled()) {
            return $resolver();
        }

        $resolved = null;
        try {
            $key = $this->dataKey($storeId, $limit);

            /** @var array<string, mixed> $value */
            $value = $this->cache->remember(
                $key,
                max(1, (int) config('scalability.product_detail_reference_cache.ttl_seconds', 300)),
                function () use ($resolver, &$resolved): array {
                    $resolved = $resolver();

                    return $this->normalize($resolved);
                },
            );

            return $value;
        } catch (Throwable) {
            return is_array($resolved) ? $resolved : $resolver();
        }
    }

    public function invalidateStore(int $storeId): void
    {
        if ($this->enabled()) {
            $this->bump($this->storeGenerationKey($storeId));
        }
    }

    public function invalidateGlobal(): void
    {
        if ($this->enabled()) {
            $this->bump($this->globalGenerationKey());
        }
    }

    private function enabled(): bool
    {
        return (bool) config('scalability.product_detail_reference_cache.enabled', false);
    }

    private function dataKey(int $storeId, int $limit): string
    {
        return $this->prefix().":store:{$storeId}:store-generation:"
            .$this->generation($this->storeGenerationKey($storeId))
            .':global-generation:'.$this->generation($this->globalGenerationKey())
            .":limit:{$limit}";
    }

    private function storeGenerationKey(int $storeId): string
    {
        return $this->prefix().":generation:store:{$storeId}";
    }

    private function globalGenerationKey(): string
    {
        return $this->prefix().':generation:global';
    }

    private function prefix(): string
    {
        return rtrim((string) config('scalability.product_detail_reference_cache.prefix'), ':');
    }

    private function generation(string $key): int
    {
        return (int) $this->cache->rememberForever($key, static fn (): int => 1);
    }

    private function bump(string $key): void
    {
        try {
            $this->cache->add($key, 1, null);
            $this->cache->increment($key);
        } catch (Throwable) {
            // Cached reference payloads also expire by TTL, so writes must not fail here.
        }
    }

    /** @param array<string, mixed> $value @return array<string, mixed> @throws JsonException */
    private function normalize(array $value): array
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> */
        return json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
    }
}
