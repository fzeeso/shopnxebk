<?php

declare(strict_types=1);

namespace Modules\Authentication\GraphQL\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Stores\Models\Store;

final class ViewerStores
{
    /** @return Collection<int, Store> */
    public function __invoke(): Collection
    {
        return auth('sanctum')->user()->stores()->wherePivot('status', 'active')->get();
    }
}
