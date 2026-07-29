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

final class UserInterfaceAccessService
{
    /**
     * @return array{
     *     platform_admin: array{
     *         interface: string,
     *         label: string,
     *         available: bool,
     *         roles: list<string>,
     *         permissions: list<string>
     *     },
     *     store_admin: array{
     *         interface: string,
     *         label: string,
     *         available: bool,
     *         stores: list<array{
     *             id: string,
     *             name: string,
     *             slug: string,
     *             status: string,
     *             roles: list<string>,
     *             permissions: list<string>
     *         }>
     *     }
     * }
     */
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
}
