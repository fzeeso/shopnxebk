<?php

declare(strict_types=1);

namespace Modules\Authentication\GraphQL\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Tenancy\Models\Tenant;

final class ViewerTenants
{
    /** @return Collection<int, Tenant> */
    public function __invoke(): Collection
    {
        return auth('sanctum')->user()->tenants()->wherePivot('status', 'active')->get();
    }
}
