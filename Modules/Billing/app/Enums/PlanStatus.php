<?php

declare(strict_types=1);

namespace Modules\Billing\Enums;

enum PlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
