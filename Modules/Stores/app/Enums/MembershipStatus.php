<?php

declare(strict_types=1);

namespace Modules\Stores\Enums;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
}
