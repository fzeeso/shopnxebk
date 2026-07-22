<?php

declare(strict_types=1);

namespace Modules\Tenancy\TenantFinder;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Tenancy\Models\Tenant;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class HeaderTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        $id = $request->header('X-Tenant-ID');
        if ($id === null || $id === '') {
            return null;
        }
        if (! Str::isUuid($id)) {
            throw new BadRequestHttpException('X-Tenant-ID must be a valid UUID.');
        }

        return Tenant::query()->whereKey($id)->first();
    }
}
