<?php

declare(strict_types=1);

namespace Modules\Tenancy\Policies;

use Modules\Authentication\Models\User;
use Modules\Tenancy\Models\Tenant;

final class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenants()->whereKey($tenant->getKey())->wherePivot('status', 'active')->exists();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->view($user, $tenant) && $user->can('tenant.manage');
    }
}
