<?php

declare(strict_types=1);

namespace Modules\Stores\Policies;

use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;

final class StorePolicy
{
    public function view(User $user, Store $store): bool
    {
        return $user->isStoreUser()
            && $user->stores()->whereKey($store->getKey())->wherePivot('status', 'active')->exists();
    }

    public function update(User $user, Store $store): bool
    {
        return $this->view($user, $store) && $user->can('manage store');
    }
}
