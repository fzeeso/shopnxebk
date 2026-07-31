<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Enums\UserInterface;
use Modules\Authentication\Models\User;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;

final class AccountInterfaceAccessService
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $platformRoles = $user->isPlatformUser() ? $this->roles($user, AccessScope::Platform) : [];
        $platformPermissions = $user->isPlatformUser() ? $this->permissions($user, AccessScope::Platform) : [];
        $stores = $user->isStoreUser()
            ? $user->stores()
                ->wherePivot('status', MembershipStatus::Active->value)
                ->orderBy('stores.name')
                ->get()
                ->map(fn (Store $store): array => [
                    'id' => $store->public_id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'status' => $store->status->value,
                    'roles' => $this->roles($user, AccessScope::Store, $store->getKey()),
                    'permissions' => $this->permissions($user, AccessScope::Store, $store->getKey()),
                ])
                ->values()
                ->all()
            : [];

        return [
            UserInterface::PlatformAdmin->value => [
                'interface' => UserInterface::PlatformAdmin->value,
                'label' => 'Platform Admin (SaaS Owner)',
                'available' => $user->isPlatformUser(),
                'roles' => $platformRoles,
                'permissions' => $platformPermissions,
                'navigation' => $this->platformNavigation($platformPermissions),
            ],
            UserInterface::StoreAdmin->value => [
                'interface' => UserInterface::StoreAdmin->value,
                'label' => 'Store Admin (Merchant)',
                'available' => $user->isStoreUser() && $stores !== [],
                'stores' => $stores,
            ],
        ];
    }

    /** @return list<string> */
    private function roles(User $user, AccessScope $scope, ?int $storeId = null): array
    {
        return $this->forAssignmentScope(
            DB::table('model_has_roles as assignments')
                ->join('roles', 'roles.id', '=', 'assignments.role_id')
                ->where('assignments.model_type', User::class)
                ->where('assignments.model_id', $user->getKey())
                ->where('roles.scope', $scope->value),
            $storeId,
        )
            ->distinct()
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /** @return list<string> */
    private function permissions(User $user, AccessScope $scope, ?int $storeId = null): array
    {
        $rolePermissions = $this->forAssignmentScope(
            DB::table('model_has_roles as assignments')
                ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'assignments.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('assignments.model_type', User::class)
                ->where('assignments.model_id', $user->getKey())
                ->where('permissions.scope', $scope->value),
            $storeId,
        )->pluck('permissions.name');

        $directPermissions = $this->forAssignmentScope(
            DB::table('model_has_permissions as assignments')
                ->join('permissions', 'permissions.id', '=', 'assignments.permission_id')
                ->where('assignments.model_type', User::class)
                ->where('assignments.model_id', $user->getKey())
                ->where('permissions.scope', $scope->value),
            $storeId,
        )->pluck('permissions.name');

        return $rolePermissions
            ->merge($directPermissions)
            ->map(static fn (mixed $name): string => (string) $name)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function forAssignmentScope(Builder $query, ?int $storeId): Builder
    {
        return $storeId === null
            ? $query->whereNull('assignments.store_id')
            : $query->where('assignments.store_id', $storeId);
    }

    /**
     * @param  list<string>  $permissions
     * @return list<array{key: string, label: string, path: string, permission: string}>
     */
    private function platformNavigation(array $permissions): array
    {
        $navigation = [];

        if (in_array('manage plans', $permissions, true)) {
            $navigation[] = [
                'key' => 'plans_pricing',
                'label' => 'Plans & Pricing',
                'path' => '/admin/plans',
                'permission' => 'manage plans',
            ];
        }

        if (in_array('manage platform settings', $permissions, true)) {
            $navigation[] = [
                'key' => 'platform_settings',
                'label' => 'Settings',
                'path' => '/admin/settings',
                'permission' => 'manage platform settings',
            ];
        }

        if (in_array('manage platform users', $permissions, true)) {
            $navigation[] = [
                'key' => 'platform_users',
                'label' => 'Admin Users',
                'path' => '/admin/users',
                'permission' => 'manage platform users',
            ];
        }

        if (in_array('manage stores', $permissions, true)) {
            $navigation[] = [
                'key' => 'merchants',
                'label' => 'Merchants',
                'path' => '/admin/merchants',
                'permission' => 'manage stores',
            ];
        }

        return $navigation;
    }
}
