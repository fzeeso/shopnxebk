<?php

declare(strict_types=1);

namespace Modules\Themes\Enums;

enum StoreThemeStatus: string
{
    case Installing = 'installing';
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
    case Failed = 'failed';
    case Blocked = 'blocked';
}
