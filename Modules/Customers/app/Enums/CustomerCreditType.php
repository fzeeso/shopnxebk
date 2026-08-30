<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum CustomerCreditType: string
{
    case Return = 'return';
    case Gift = 'gift';
    case Adjustment = 'adjustment';
}
