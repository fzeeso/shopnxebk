<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaVariantName: string
{
    case Thumbnail = 'thumbnail';
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
    case Original = 'original';
}
