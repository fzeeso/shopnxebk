<?php

declare(strict_types=1);

namespace Modules\Tenancy\Contracts;

use Modules\Authentication\Models\User;
use Modules\Tenancy\Models\Tenant;

interface TenantProvisioner
{
    public function provision(User $owner, string $name, string $slug): Tenant;
}
