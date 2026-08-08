<?php

declare(strict_types=1);

namespace Modules\Stores\Enums;

enum StorePolicyStatus: string
{
    case Disabled = 'disabled';
    case Draft = 'draft';
    case Published = 'published';
}
