<?php

declare(strict_types=1);

namespace Modules\Tenancy\TenantContext;

use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RequestTenantContext implements TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function require(): Tenant
    {
        return $this->tenant ?? throw new BadRequestHttpException('X-Tenant-ID is required for this operation.');
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
