<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Enums\StorePolicyStatus;
use Modules\Stores\Models\PolicyType;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StorePolicy;

final readonly class StorePolicyService
{
    public function __construct(
        private StoreContext $context,
        private StoreAccessService $access,
    ) {}

    /** @return Collection<int, StorePolicy> */
    public function list(User $user): Collection
    {
        $store = $this->store($user, false);

        return StorePolicy::query()
            ->where('store_id', $store->getKey())
            ->with($this->relations())
            ->join('policy_types', 'policy_types.id', '=', 'store_policies.policy_type_id')
            ->select('store_policies.*')
            ->orderBy('policy_types.sort_order')
            ->orderBy('store_policies.title')
            ->get();
    }

    public function show(User $user, StorePolicy $policy): StorePolicy
    {
        $store = $this->store($user, false);
        $this->ensureOwned($policy, $store);

        return $this->load($policy);
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): StorePolicy
    {
        $store = $this->store($user, true);
        $policyType = PolicyType::query()->where('public_id', $data['policy_type_id'])->firstOrFail();
        $slug = Str::slug((string) ($data['slug'] ?? $data['title']));
        if ($slug === '') {
            throw ValidationException::withMessages([
                'slug' => ['A Latin-letter or numeric slug is required for this title.'],
            ]);
        }
        $this->ensureUnique($store, $policyType, $slug);

        $policy = StorePolicy::query()->create([
            'store_id' => $store->getKey(),
            'policy_type_id' => $policyType->getKey(),
            'title' => $data['title'],
            'slug' => $slug,
            'status' => StorePolicyStatus::Draft,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        return $this->load($policy);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, StorePolicy $policy, array $data): StorePolicy
    {
        $store = $this->store($user, true);
        $this->ensureOwned($policy, $store);
        $policy->loadMissing('policyType');
        $slug = isset($data['slug']) ? Str::slug((string) $data['slug']) : $policy->slug;
        $this->ensureUnique($store, $policy->policyType, $slug, $policy);

        $policy->fill([
            ...$data,
            'slug' => $slug,
            'updated_by' => $user->getKey(),
        ])->save();

        return $this->load($policy->refresh());
    }

    public function publish(User $user, StorePolicy $policy): StorePolicy
    {
        $store = $this->store($user, true);
        $this->ensureOwned($policy, $store);

        if (! $policy->translations()->whereRaw("BTRIM(content) <> ''")->exists()) {
            throw ValidationException::withMessages([
                'translations' => ['At least one non-empty translation is required before publishing.'],
            ]);
        }

        $policy->forceFill([
            'status' => StorePolicyStatus::Published,
            'published_at' => $policy->published_at ?? now(),
            'updated_by' => $user->getKey(),
        ])->save();

        return $this->load($policy->refresh());
    }

    public function unpublish(User $user, StorePolicy $policy): StorePolicy
    {
        $store = $this->store($user, true);
        $this->ensureOwned($policy, $store);
        $policy->forceFill([
            'status' => StorePolicyStatus::Draft,
            'published_at' => null,
            'updated_by' => $user->getKey(),
        ])->save();

        return $this->load($policy->refresh());
    }

    public function delete(User $user, StorePolicy $policy): void
    {
        $store = $this->store($user, true);
        $this->ensureOwned($policy, $store);
        $policy->delete();
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage ? $this->access->ensureCanManagePolicies($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function ensureOwned(StorePolicy $policy, Store $store): void
    {
        if ($policy->store_id !== $store->getKey()) {
            abort(404);
        }
    }

    private function ensureUnique(Store $store, PolicyType $type, string $slug, ?StorePolicy $except = null): void
    {
        $typeExists = StorePolicy::query()
            ->where('store_id', $store->getKey())
            ->where('policy_type_id', $type->getKey())
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();
        if ($typeExists) {
            throw ValidationException::withMessages([
                'policy_type_id' => ['This Store already has a policy for the selected type.'],
            ]);
        }

        $slugExists = StorePolicy::query()
            ->where('store_id', $store->getKey())
            ->where('slug', $slug)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();
        if ($slugExists) {
            throw ValidationException::withMessages([
                'slug' => ['This slug is already used by another Store policy.'],
            ]);
        }
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['store', 'policyType', 'translations.language', 'creator', 'updater'];
    }

    private function load(StorePolicy $policy): StorePolicy
    {
        return $policy->load($this->relations());
    }
}
