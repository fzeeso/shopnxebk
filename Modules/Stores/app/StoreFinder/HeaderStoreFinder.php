<?php

declare(strict_types=1);

namespace Modules\Stores\StoreFinder;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Stores\Models\Store;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class HeaderStoreFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        $id = $request->header('X-Store-ID');
        if ($id === null || $id === '') {
            return null;
        }
        if (! Str::isUlid($id)) {
            throw new BadRequestHttpException('X-Store-ID must be a valid ULID.');
        }

        return Store::query()->where('public_id', $id)->first();
    }
}
