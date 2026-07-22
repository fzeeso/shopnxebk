<?php

declare(strict_types=1);

namespace App\Support\Search;

use Laravel\Scout\Builder;
use Modules\Tenancy\Contracts\TenantContext;

final readonly class TenantSearch
{
    public function __construct(private TenantContext $context) {}

    /** @param class-string $searchableModel */
    public function for(string $searchableModel, string $query = ''): Builder
    {
        return $searchableModel::search($query)->where('tenant_id', $this->context->require()->getKey());
    }
}
