<?php

declare(strict_types=1);

namespace App\Queue;

use Spatie\Multitenancy\Jobs\NotTenantAware;

abstract class GlobalJob implements NotTenantAware
{
    /** Marker base for jobs that must never inherit tenant context. */
}
