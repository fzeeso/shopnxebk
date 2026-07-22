<?php

declare(strict_types=1);

namespace Modules\Tenancy\Cache;

use Modules\Tenancy\Contracts\TenantContext;

final readonly class TenantCacheKey
{
    public function __construct(private TenantContext $context) {}

    public function for(string $key): string
    {
        return 'tenant:'.$this->context->require()->getKey().':'.$key;
    }
}
