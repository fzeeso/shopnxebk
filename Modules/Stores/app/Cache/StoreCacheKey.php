<?php

declare(strict_types=1);

namespace Modules\Stores\Cache;

use Modules\Stores\Contracts\StoreContext;

final readonly class StoreCacheKey
{
    public function __construct(private StoreContext $context) {}

    public function for(string $key): string
    {
        return 'store:'.$this->context->require()->getKey().':'.$key;
    }
}
