<?php

declare(strict_types=1);

namespace Modules\Themes\Enums;

enum ThemeSourceType: string
{
    case Platform = 'platform';
    case ThirdParty = 'third_party';
    case Custom = 'custom';
}
