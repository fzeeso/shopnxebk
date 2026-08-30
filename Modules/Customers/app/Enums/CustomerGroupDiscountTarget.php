<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum CustomerGroupDiscountTarget: string
{
    case Category = 'category';
    case Product = 'product';
}
