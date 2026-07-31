<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Modules\Authentication\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class PlatformStoreAccessService
{
    public function ensureCanManageStores(User $user): void
    {
        if (! $user->isPlatformUser()) {
            throw new AccessDeniedHttpException('Platform-scoped account required.');
        }

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            if (! $user->can('manage stores')) {
                throw new AccessDeniedHttpException('The manage stores permission is required.');
            }
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function ensureCanManageMerchants(User $user): void
    {
        $this->ensureCanManageStores($user);
    }
}
