<?php

declare(strict_types=1);

namespace Modules\Stores\GraphQL\Queries;

use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class ActiveStore
{
    public function __construct(private StoreContext $context) {}

    public function __invoke(): Store
    {
        return $this->context->require();
    }
}
