<?php

declare(strict_types=1);

namespace Modules\Stores\Enums;

enum PageStatus: string
{
    case Disabled = 'disabled';
    case Draft = 'draft';
    case Published = 'published';
}
