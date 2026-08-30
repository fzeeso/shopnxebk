<?php

declare(strict_types=1);

namespace Modules\Customers\Services;

use Modules\Customers\Contracts\CustomerGroupResolver;
use Modules\Customers\Data\CustomerGroupReference;
use Modules\Customers\Models\CustomerGroup;
use Modules\Stores\Models\Store;

final class CustomerGroupReferenceService implements CustomerGroupResolver
{
    public function resolve(Store $store, string $publicId): CustomerGroupReference
    {
        $group = CustomerGroup::query()
            ->withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();

        return new CustomerGroupReference(
            id: (int) $group->getKey(),
            publicId: (string) $group->public_id,
            code: (string) $group->code,
        );
    }
}
