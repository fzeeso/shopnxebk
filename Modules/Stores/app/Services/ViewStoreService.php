<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;

final readonly class ViewStoreService
{
    public function __construct(private StoreAccessService $access) {}

    public function view(User $user, Store $store): Store
    {
        $this->access->ensureCanView($user, $store);

        return $store->refresh();
    }
}
