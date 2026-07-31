<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use DomainException;
use Illuminate\Support\Collection;
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

    /** @return list<string> */
    public function platformRoleNames(): array
    {
        $this->authorizationCatalog->ensure();

        return Role::query()
            ->where('guard_name', 'web')
            ->where('scope', AccessScope::Platform->value)
            ->whereNull('store_id')
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /** @return list<string> */
    public function storeRoleNames(?Store $store = null): array
    {
        $this->authorizationCatalog->ensure();

        return Role::query()
            ->where('guard_name', 'web')
            ->where('scope', AccessScope::Store->value)
            ->where(function ($query) use ($store): void {
                $query->whereNull('store_id');
                if ($store !== null) {
                    $query->orWhere('store_id', $store->getKey());
                }
            })
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<string> $roleNames */
    public function syncPlatformRoles(User $user, array $roleNames): void
    {
        if (! $user->isPlatformUser()) {
            throw new DomainException('Only Platform-scoped users may receive Platform roles.');
        }

        $roles = $this->platformRoles($roleNames);
        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            $user->syncRoles($roles);
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /** @param list<string> $roleNames */
    public function syncStoreRoles(User $user, Store $store, array $roleNames): void
    {
        if (! $user->isStoreUser()) {
            throw new DomainException('Only Store-scoped users may receive Store roles.');
        }

        if (! StoreMembership::query()
            ->where('user_id', $user->getKey())
            ->where('store_id', $store->getKey())
            ->where('status', MembershipStatus::Active->value)
            ->exists()) {
            throw new DomainException('Active Store membership is required before assigning Store roles.');
        }

        $roles = $this->storeRoles($store, $roleNames);
        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId($store->getKey());

        try {
            $user->syncRoles($roles);
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * @param  list<string>  $roleNames
     * @return Collection<int, Role>
     */
    private function platformRoles(array $roleNames): Collection
    {
        $this->authorizationCatalog->ensure();
        $names = collect($roleNames)->map(static fn (string $name): string => trim($name))->filter()->unique()->values();
        if ($names->isEmpty()) {
            throw new DomainException('At least one Platform role is required.');
        }

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->where('scope', AccessScope::Platform->value)
            ->whereNull('store_id')
            ->whereIn('name', $names)
            ->get();

        if ($roles->count() !== $names->count()) {
            throw new DomainException('One or more Platform roles are invalid.');
        }

        return $roles;
    }

    /**
     * @param  list<string>  $roleNames
     * @return Collection<int, Role>
     */
    private function storeRoles(Store $store, array $roleNames): Collection
    {
        $this->authorizationCatalog->ensure();
        $names = collect($roleNames)->map(static fn (string $name): string => trim($name))->filter()->unique()->values();
        if ($names->isEmpty()) {
            throw new DomainException('At least one Store role is required.');
        }

        $roles = $names->map(function (string $name) use ($store): Role {
            $role = Role::query()
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->where('scope', AccessScope::Store->value)
                ->where(function ($query) use ($store): void {
                    $query->whereNull('store_id')->orWhere('store_id', $store->getKey());
                })
                ->orderByRaw('CASE WHEN store_id = ? THEN 0 ELSE 1 END', [$store->getKey()])
                ->first();

            if ($role === null) {
                throw new DomainException("Store role [{$name}] does not exist for this Store.");
            }

            return $role;
        });

        return $roles;
    }
}
