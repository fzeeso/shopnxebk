<?php

declare(strict_types=1);

namespace Modules\Tenancy\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\Permission;
use Modules\Authentication\Models\Role;
use Modules\Authentication\Models\User;
use Modules\Tenancy\Contracts\TenantProvisioner;
use Modules\Tenancy\Enums\MembershipStatus;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Events\TenantCreated;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Models\TenantMembership;

final class ProvisionTenant implements TenantProvisioner
{
    public function provision(User $owner, string $name, string $slug): Tenant
    {
        $tenant = Tenant::query()->create(['name' => $name, 'slug' => $slug, 'status' => TenantStatus::Active, 'settings' => [], 'metadata' => []]);
        TenantMembership::query()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $owner->getKey(), 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($tenant->getKey());
        try {
            foreach (['tenant.access', 'tenant.manage', 'members.manage', 'roles.manage'] as $name) {
                Permission::findOrCreate($name, 'web');
            }
            $ownerRole = Role::findOrCreate('owner', 'web');
            $ownerRole->syncPermissions(['tenant.access', 'tenant.manage', 'members.manage', 'roles.manage']);
            Role::findOrCreate('admin', 'web')->syncPermissions(['tenant.access', 'members.manage']);
            Role::findOrCreate('staff', 'web')->syncPermissions(['tenant.access']);
            $owner->assignRole($ownerRole);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        DB::afterCommit(fn () => TenantCreated::dispatch($tenant->getKey(), $owner->getKey()));

        return $tenant;
    }
}
