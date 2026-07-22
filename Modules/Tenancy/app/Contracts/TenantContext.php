<?php

declare(strict_types=1);

namespace Modules\Tenancy\Contracts;

use Modules\Tenancy\Models\Tenant;

interface TenantContext
{
    public function set(Tenant $tenant): void;

    public function current(): ?Tenant;

    public function id(): ?string;

    public function require(): Tenant;

    public function clear(): void;
}
