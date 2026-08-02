<?php

declare(strict_types=1);

namespace Modules\Stores\Enums;

enum StoreStatus: string
{
    case Draft = 'draft';
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Frozen = 'frozen';
    case Closed = 'closed';
}
