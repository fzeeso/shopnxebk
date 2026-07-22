<?php

declare(strict_types=1);

namespace Modules\Tenancy\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
