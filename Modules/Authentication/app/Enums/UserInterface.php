<?php

declare(strict_types=1);

namespace Modules\Authentication\Enums;

enum UserInterface: string
{
    case PlatformAdmin = 'platform_admin';
    case StoreAdmin = 'store_admin';
}
