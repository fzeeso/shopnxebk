<?php

declare(strict_types=1);

namespace Modules\Stores\Enums;

enum StorePolicyStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
