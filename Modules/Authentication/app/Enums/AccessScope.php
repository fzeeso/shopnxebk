<?php

declare(strict_types=1);

namespace Modules\Authentication\Enums;

enum AccessScope: string
{
    case Platform = 'platform';
    case Store = 'store';
}
