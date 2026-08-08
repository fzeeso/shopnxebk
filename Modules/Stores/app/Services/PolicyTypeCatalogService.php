<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Settings\Services\PlatformSettingsAccessService;
use Modules\Stores\Actions\EnsureStorePolicyCatalog;
use Modules\Stores\Models\PolicyType;
use Modules\Stores\Models\Store;

final readonly class PolicyTypeCatalogService
{
    public function __construct(
        private PlatformSettingsAccessService $platformAccess,
        private StoreAccessService $storeAccess,
        private EnsureStorePolicyCatalog $storePolicies,
    ) {}

    /** @return LengthAwarePaginator<int, PolicyType> */
    public function listPlatform(User $user, int $perPage = 25): LengthAwarePaginator
    {
        $this->platformAccess->ensureCanView($user);

        return PolicyType::query()->orderBy('sort_order')->orderBy('name')->paginate($perPage);
    }

    /** @return Collection<int, PolicyType> */
    public function listForStore(User $user, Store $store): Collection
    {
        $this->storeAccess->ensureCanView($user, $store);

        return PolicyType::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function createPlatform(User $user, array $data): PolicyType
    {
        $this->platformAccess->ensureCanManage($user);

        return DB::transaction(function () use ($data): PolicyType {
            $policyType = PolicyType::query()->create([
                ...$data,
                'is_system' => false,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->storePolicies->ensureTypeForAllStores($policyType);

            return $policyType;
        });
    }

    /** @param array<string, mixed> $data */
    public function updatePlatform(User $user, PolicyType $policyType, array $data): PolicyType
    {
        $this->platformAccess->ensureCanManage($user);

        if ($policyType->is_system && isset($data['code']) && $data['code'] !== $policyType->code) {
            throw ValidationException::withMessages([
                'code' => ['System policy type codes are immutable.'],
            ]);
        }

        $policyType->fill($data)->save();

        return $policyType->refresh();
    }

    public function deletePlatform(User $user, PolicyType $policyType): void
    {
        $this->platformAccess->ensureCanManage($user);

        if ($policyType->is_system) {
            throw ValidationException::withMessages([
                'policy_type' => ['System policy types cannot be deleted.'],
            ]);
        }
        if ($policyType->policies()->exists()) {
            throw ValidationException::withMessages([
                'policy_type' => ['A policy type assigned to a Store cannot be deleted.'],
            ]);
        }

        $policyType->delete();
    }
}
