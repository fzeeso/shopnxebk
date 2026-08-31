<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

enum IdempotencyMode: string
{
    case Required = 'required';
    case Supported = 'supported';
    case Excluded = 'excluded';
}
