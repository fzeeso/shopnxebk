<?php

declare(strict_types=1);

namespace Modules\Stores\Enums;

enum BusinessType: string
{
    case Ecommerce = 'ecommerce';
    case B2B = 'b2b';
    case Services = 'services';
    case Digital = 'digital';
    case Restaurant = 'restaurant';
    case Marketplace = 'marketplace';
}
