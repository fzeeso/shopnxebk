<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
