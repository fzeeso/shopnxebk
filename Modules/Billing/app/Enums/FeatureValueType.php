<?php

declare(strict_types=1);

namespace Modules\Billing\Enums;

enum FeatureValueType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Text = 'text';
}
