<?php

declare(strict_types=1);

namespace Modules\Stores\Enums;

enum PageType: string
{
    case Content = 'content';
    case Contact = 'contact';
    case ExternalLink = 'external_link';
    case Rss = 'rss';
}
