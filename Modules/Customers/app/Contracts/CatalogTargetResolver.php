<?php

declare(strict_types=1);

namespace Modules\Customers\Contracts;

use Modules\Customers\Data\CatalogTargetReference;
use Modules\Customers\Enums\CustomerGroupDiscountTarget;
use Modules\Stores\Models\Store;

interface CatalogTargetResolver
{
    public function resolve(
        Store $store,
        CustomerGroupDiscountTarget $type,
        string $publicId,
    ): CatalogTargetReference;
}
