<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use DomainException;
use Modules\Authentication\Actions\EnsureAuthorizationCatalog;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\Role;
use Modules\Authentication\Models\User;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;

final readonly class ScopedRoleAssignmentService
{
    public function __construct(private EnsureAuthorizationCatalog $authorizationCatalog) {}

    public function assignPlatformRole(User $user, string $roleName): void
    {
        if (! $user->isPlatformUser()) {
            throw new DomainException('Only Platform-scoped users may receive Platform roles.');
        }

        $this->authorizationCatalog->ensure();
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('scope', AccessScope::Platform->value)
            ->whereNull('store_id')
            ->first();

        if ($role === null) {
            throw new DomainException("Platform role [{$roleName}] does not exist.");
        }

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);
        try {
            $user->assignRole($role);
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function assignStoreRole(User $user, Store $store, string $roleName): void
    {
        if (! $user->isStoreUser()) {
            throw new DomainException('Only Store-scoped users may receive Store roles.');
        }

        if (! StoreMembership::query()
            ->where('user_id', $user->getKey())
            ->where('store_id', $store->getKey())
            ->where('status', MembershipStatus::Active->value)
            ->exists()) {
            throw new DomainException('Active Store membership is required before assigning a Store role.');
        }

        $this->authorizationCatalog->ensure();
        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('scope', AccessScope::Store->value)
            ->where(function ($query) use ($store): void {
                $query->whereNull('store_id')->orWhere('store_id', $store->getKey());
            })
            ->orderByRaw('CASE WHEN store_id = ? THEN 0 ELSE 1 END', [$store->getKey()])
            ->first();

        if ($role === null) {
            throw new DomainException("Store role [{$roleName}] does not exist for this Store.");
        }

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId($store->getKey());
        try {
            $user->assignRole($role);
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
