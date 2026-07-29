<?php

declare(strict_types=1);

namespace Modules\Authentication\Actions;

use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\Permission;
use Modules\Authentication\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class EnsureAuthorizationCatalog
{
    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'Super Admin' => [
            'manage stores',
            'manage plans',
            'manage subscriptions',
            'impersonate store',
            'manage marketplace',
            'manage platform settings',
        ],
        'Support' => [
            'manage stores',
            'impersonate store',
        ],
        'Billing' => [
            'manage plans',
            'manage subscriptions',
        ],
        'Owner' => [
            'access store',
            'manage store',
            'manage store members',
            'manage store roles',
            'manage products',
            'manage orders',
            'manage customers',
            'manage discounts',
        ],
        'Manager' => [
            'access store',
            'manage store',
            'manage store members',
            'manage products',
            'manage orders',
            'manage customers',
            'manage discounts',
        ],
        'Sales' => [
            'access store',
            'manage orders',
            'manage customers',
            'manage discounts',
        ],
        'Inventory' => [
            'access store',
            'manage products',
        ],
    ];

    /** @var list<string> */
    private const PLATFORM_ROLES = ['Super Admin', 'Support', 'Billing'];

    /** @var list<string> */
    private const PLATFORM_PERMISSIONS = [
        'manage stores',
        'manage plans',
        'manage subscriptions',
        'impersonate store',
        'manage marketplace',
        'manage platform settings',
    ];

    public function ensure(): void
    {
        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
                foreach ($permissionNames as $permissionName) {
                    $scope = in_array($permissionName, self::PLATFORM_PERMISSIONS, true)
                        ? AccessScope::Platform
                        : AccessScope::Store;
                    Permission::query()->firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                        'scope' => $scope,
                    ]);
                }

                $role = Role::query()->firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'store_id' => null,
                    'scope' => in_array($roleName, self::PLATFORM_ROLES, true)
                        ? AccessScope::Platform
                        : AccessScope::Store,
                ]);
                $role->syncPermissions($permissionNames);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            setPermissionsTeamId($previousStoreId);
        }
    }
}
