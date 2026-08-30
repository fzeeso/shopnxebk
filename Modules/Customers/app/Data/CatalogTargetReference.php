<?php

declare(strict_types=1);

namespace Modules\Customers\Data;

use Modules\Customers\Enums\CustomerGroupDiscountTarget;

final readonly class CatalogTargetReference
{
    public function __construct(
        public int $id,
        public string $publicId,
        public CustomerGroupDiscountTarget $type,
    ) {}
}
