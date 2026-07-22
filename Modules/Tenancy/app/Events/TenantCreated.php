<?php

declare(strict_types=1);

namespace Modules\Tenancy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TenantCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly string $tenantId, public readonly string $ownerId) {}
}
