<?php

declare(strict_types=1);

namespace Modules\Tenancy\GraphQL\Queries;

use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Models\Tenant;

final readonly class ActiveTenant
{
    public function __construct(private TenantContext $context) {}

    public function __invoke(): Tenant
    {
        return $this->context->require();
    }
}
