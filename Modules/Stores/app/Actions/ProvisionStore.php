<?php

declare(strict_types=1);

namespace Modules\Stores\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Models\Role;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreProvisioner;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Events\StoreCreated;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;

final readonly class ProvisionStore implements StoreProvisioner
{
    public function __construct(private EnsureAuthorizationCatalog $authorizationCatalog) {}

    public function provision(User $owner, string $name, string $slug): Store
    {
        $store = Store::query()->create(['name' => $name, 'slug' => $slug, 'status' => StoreStatus::Active, 'settings' => [], 'metadata' => []]);
        StoreMembership::query()->create(['store_id' => $store->getKey(), 'user_id' => $owner->getKey(), 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $previousTeamId = getPermissionsTeamId();
        $this->authorizationCatalog->ensure();
        setPermissionsTeamId($store->getKey());
        try {
            $ownerRole = Role::findByName('Owner', 'web');
            $owner->assignRole($ownerRole);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        DB::afterCommit(fn () => StoreCreated::dispatch($store->getKey(), $owner->getKey()));

        return $store;
    }
}
