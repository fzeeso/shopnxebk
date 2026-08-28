<?php

declare(strict_types=1);

namespace Modules\Stores\StoreFinder;

use Illuminate\Contracts\Cache\Repository;
use Modules\Stores\Models\Store;
use Throwable;

final readonly class StoreLookupCache
{
    public function __construct(private Repository $cache) {}

    public function findByPublicId(string $publicId): ?Store
    {
        if (! $this->enabled()) {
            return $this->query($publicId);
        }

        try {
            $attributes = $this->cache->get($this->key($publicId));
            if (is_array($attributes)) {
                return (new Store)->newFromBuilder($attributes);
            }
        } catch (Throwable) {
            // Cache is an optimization. Store resolution remains available through PostgreSQL.
        }

        $store = $this->query($publicId);
        if ($store === null) {
            return null;
        }

        try {
            $this->cache->put(
                $this->key($publicId),
                $store->getAttributes(),
                max(1, (int) config('scalability.store_lookup_cache.ttl_seconds', 30)),
            );
        } catch (Throwable) {
            // A cache write failure must not turn an authorized request into an outage.
        }

        return $store;
    }

    public function forget(Store $store): void
    {
        if (! $this->enabled()) {
            return;
        }

        $publicIds = array_unique(array_filter([
            $store->getRawOriginal('public_id'),
            $store->getAttribute('public_id'),
        ], static fn (mixed $value): bool => is_string($value) && $value !== ''));

        foreach ($publicIds as $publicId) {
            try {
                $this->cache->forget($this->key($publicId));
            } catch (Throwable) {
                // The TTL is the final safety net when invalidation cannot reach Redis.
            }
        }
    }

    private function enabled(): bool
    {
        return (bool) config('scalability.store_lookup_cache.enabled', false);
    }

    private function key(string $publicId): string
    {
        return rtrim((string) config('scalability.store_lookup_cache.prefix'), ':')
            .':'.strtolower($publicId);
    }

    private function query(string $publicId): ?Store
    {
        return Store::query()->where('public_id', $publicId)->first();
    }
}
