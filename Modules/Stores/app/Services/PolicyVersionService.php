<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\PolicyVersion;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StorePolicy;
use Modules\Stores\Models\StorePolicyTranslation;

final readonly class PolicyVersionService
{
    public function __construct(
        private StoreContext $context,
        private StoreAccessService $access,
    ) {}

    /** @return Collection<int, PolicyVersion> */
    public function list(User $user, StorePolicy $policy): Collection
    {
        $store = $this->store($user, false);
        $this->ensureOwned($policy, $store);

        return PolicyVersion::query()
            ->where('store_policy_id', $policy->getKey())
            ->with(['language', 'creator'])
            ->orderBy('language_id')
            ->orderByDesc('version')
            ->get();
    }

    public function restore(User $user, StorePolicy $policy, PolicyVersion $source): PolicyVersion
    {
        $store = $this->store($user, true);
        $this->ensureOwned($policy, $store);
        if ($source->store_policy_id !== $policy->getKey()) {
            abort(404);
        }

        return DB::transaction(function () use ($policy, $source, $user): PolicyVersion {
            StorePolicy::query()->whereKey($policy->getKey())->lockForUpdate()->firstOrFail();
            $translation = StorePolicyTranslation::query()
                ->where('store_policy_id', $policy->getKey())
                ->where('language_id', $source->language_id)
                ->first();
            if (! $translation instanceof StorePolicyTranslation) {
                throw ValidationException::withMessages([
                    'version' => ['Restore requires an existing translation for this language.'],
                ]);
            }

            $translation->forceFill(['content' => $source->content])->save();
            $versionNumber = ((int) PolicyVersion::query()
                ->where('store_policy_id', $policy->getKey())
                ->where('language_id', $source->language_id)
                ->max('version')) + 1;
            $restored = PolicyVersion::query()->create([
                'store_policy_id' => $policy->getKey(),
                'language_id' => $source->language_id,
                'version' => $versionNumber,
                'content' => $source->content,
                'created_by' => $user->getKey(),
            ]);
            $policy->forceFill(['updated_by' => $user->getKey()])->save();

            return $restored->load(['language', 'creator']);
        });
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
}
