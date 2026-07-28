<?php

declare(strict_types=1);

namespace Modules\Stores\Enums;

enum StoreStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
