<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Modules\Authentication\Models\User;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreMembership;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class StoreAccessService
{
    public function ensureCanView(User $user, Store $store): void
    {
        if (! $user->isStoreUser()) {
            throw new AccessDeniedHttpException('Store-scoped account required.');
        }

        if (! StoreMembership::query()
            ->where('store_id', $store->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', MembershipStatus::Active->value)
            ->exists()) {
            throw new AccessDeniedHttpException('Active Store membership is required.');
        }
    }

    public function ensureCanManage(User $user, Store $store): void
    {
        $this->ensureCanView($user, $store);

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId($store->getKey());

        try {
            if (! $user->can('manage store')) {
                throw new AccessDeniedHttpException('The manage store permission is required.');
            }
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
