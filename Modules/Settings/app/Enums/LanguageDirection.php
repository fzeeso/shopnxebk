<?php

declare(strict_types=1);

namespace Modules\Settings\Enums;

enum LanguageDirection: string
{
    case LeftToRight = 'ltr';
    case RightToLeft = 'rtl';
}
