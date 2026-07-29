<?php

declare(strict_types=1);

namespace Modules\Billing\Enums;

enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';
}
