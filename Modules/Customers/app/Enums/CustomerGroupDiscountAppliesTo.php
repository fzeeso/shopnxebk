<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum CustomerGroupDiscountAppliesTo: string
{
    case CategoryOnly = 'category_only';
    case CategoryAndDescendants = 'category_and_descendants';
    case NotApplicable = 'not_applicable';
}
