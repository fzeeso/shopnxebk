<?php

declare(strict_types=1);

namespace Modules\Themes\Enums;

enum ThemeVersionStatus: string
{
    case Uploaded = 'uploaded';
    case Scanning = 'scanning';
    case Validating = 'validating';
    case ValidationFailed = 'validation_failed';
    case ReadyForReview = 'ready_for_review';
    case Approved = 'approved';
    case Published = 'published';
    case Deprecated = 'deprecated';
    case Blocked = 'blocked';
}
