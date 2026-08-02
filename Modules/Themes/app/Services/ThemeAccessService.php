<?php

declare(strict_types=1);

namespace Modules\Themes\Services;

use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Stores\Services\StoreAccessService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ThemeAccessService
{
    public function __construct(private StoreAccessService $stores) {}

    public function ensureCanManageMarketplace(User $user): void
    {
        if (! $user->isPlatformUser()) {
            throw new AccessDeniedHttpException('Platform-scoped account required.');
        }

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);
        try {
            if (! $user->can('manage marketplace')) {
                throw new AccessDeniedHttpException('The manage marketplace permission is required.');
            }
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function ensureCanManageStoreThemes(User $user, Store $store): void
    {
        $this->stores->ensureCanView($user, $store);

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId($store->getKey());
        try {
            if (! $user->can('manage themes')) {
                throw new AccessDeniedHttpException('The manage themes permission is required.');
            }
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
