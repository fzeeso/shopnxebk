<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum CustomerGroupCategoryAccess: string
{
    case None = 'none';
    case All = 'all';
    case Specific = 'specific';
}
