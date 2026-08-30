<?php

declare(strict_types=1);

namespace Modules\Customers\Contracts;

use Modules\Customers\Data\CustomerGroupReference;
use Modules\Stores\Models\Store;

interface CustomerGroupResolver
{
    public function resolve(Store $store, string $publicId): CustomerGroupReference;
}
