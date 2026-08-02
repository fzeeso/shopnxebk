<?php

declare(strict_types=1);

namespace Modules\Themes\Enums;

enum ThemeStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Published = 'published';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
    case Retired = 'retired';
}
