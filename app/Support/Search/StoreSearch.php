<?php

declare(strict_types=1);

namespace App\Support\Search;

use Laravel\Scout\Builder;
use Modules\Stores\Contracts\StoreContext;

final readonly class StoreSearch
{
    public function __construct(private StoreContext $context) {}

    /** @param class-string $searchableModel */
    public function for(string $searchableModel, string $query = ''): Builder
    {
        return $searchableModel::search($query)->where('store_id', $this->context->require()->getKey());
    }
}
