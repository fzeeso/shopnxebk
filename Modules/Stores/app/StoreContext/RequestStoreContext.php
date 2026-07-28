<?php

declare(strict_types=1);

namespace Modules\Stores\StoreContext;

use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RequestStoreContext implements StoreContext
{
    private ?Store $store = null;

    public function set(Store $store): void
    {
        $this->store = $store;
    }

    public function current(): ?Store
    {
        return $this->store;
    }

    public function id(): ?int
    {
        return $this->store?->getKey();
    }

    public function require(): Store
    {
        return $this->store ?? throw new BadRequestHttpException('X-Store-ID is required for this operation.');
    }

    public function clear(): void
    {
        $this->store = null;
    }
}
