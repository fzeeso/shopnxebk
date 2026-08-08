<?php

declare(strict_types=1);

namespace Modules\Stores\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Authentication\Models\User;
use Modules\Stores\Enums\StorePolicyStatus;
use Modules\Stores\Models\PolicyType;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StorePolicy;

final readonly class EnsureStorePolicyCatalog
{
    public function __construct(private EnsurePolicyTypeCatalog $policyTypes) {}

    public function ensureForStore(Store $store, ?User $actor = null): void
    {
        $this->policyTypes->ensure();

        $this->ensureTypes(
            $store,
            PolicyType::query()->orderBy('sort_order')->orderBy('id')->get(),
            $actor,
        );
    }

    public function ensureForAllStores(): void
    {
        $this->policyTypes->ensure();
        $types = PolicyType::query()->orderBy('sort_order')->orderBy('id')->get();

        Store::query()->withoutGlobalScopes()->eachById(function (Store $store) use ($types): void {
            $this->ensureTypes($store, $types);
        });
    }

    public function ensureTypeForAllStores(PolicyType $type): void
    {
        Store::query()->withoutGlobalScopes()->eachById(function (Store $store) use ($type): void {
            $this->ensureType($store, $type);
        });
    }

    /** @param Collection<int, PolicyType> $types */
    private function ensureTypes(Store $store, Collection $types, ?User $actor = null): void
    {
        foreach ($types as $type) {
            $this->ensureType($store, $type, $actor);
        }
    }

    private function ensureType(Store $store, PolicyType $type, ?User $actor = null): void
    {
        $exists = StorePolicy::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->where('policy_type_id', $type->getKey())
            ->exists();

        if ($exists) {
            return;
        }

        StorePolicy::query()->withoutGlobalScopes()->create([
            'store_id' => $store->getKey(),
            'policy_type_id' => $type->getKey(),
            'title' => $type->name,
            'slug' => $this->availableSlug($store, $type),
            'status' => StorePolicyStatus::Disabled,
            'published_at' => null,
            'created_by' => $actor?->getKey(),
            'updated_by' => $actor?->getKey(),
        ]);
    }

    private function availableSlug(Store $store, PolicyType $type): string
    {
        $base = Str::slug($type->code);
        if ($base === '') {
            $base = 'policy-'.$type->getKey();
        }

        $slug = $base;
        $suffix = 2;
        while (StorePolicy::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->where('slug', $slug)
            ->exists()) {
            $slug = Str::limit($base, 150, '').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
