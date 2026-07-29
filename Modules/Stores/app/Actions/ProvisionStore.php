<?php

declare(strict_types=1);

namespace Modules\Stores\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\ScopedRoleAssignmentService;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Events\StoreCreated;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;

final readonly class ProvisionStore implements StoreProvisioner
{
    public function __construct(private ScopedRoleAssignmentService $roleAssignments) {}

    public function provision(User $owner, string $name, string $slug): Store
    {
        if (! $owner->isStoreUser()) {
            throw new \DomainException('Only Store-scoped users may own or provision Stores.');
        }

        $store = Store::query()->create(['name' => $name, 'legal_name' => $name, 'slug' => $slug, 'status' => StoreStatus::Active, 'settings' => [], 'metadata' => []]);
        StoreMembership::query()->create(['store_id' => $store->getKey(), 'user_id' => $owner->getKey(), 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->roleAssignments->assignStoreRole($owner, $store, 'Owner');

        DB::afterCommit(fn () => StoreCreated::dispatch($store->getKey(), $owner->getKey()));

        return $store;
    }
}
