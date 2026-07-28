<?php

declare(strict_types=1);

namespace Modules\Stores\Contracts;

use Modules\Stores\Models\Store;

interface StoreContext
{
    public function set(Store $store): void;

    public function current(): ?Store;

    public function id(): ?int;

    public function require(): Store;

    public function clear(): void;
}
