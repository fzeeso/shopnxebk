<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use Modules\Authentication\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class PlatformUserAccessService
{
    public function ensureCanManage(User $user): void
    {
        if (! $user->isPlatformUser()) {
            throw new AccessDeniedHttpException('Platform-scoped account required.');
        }

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            if (! $user->can('manage platform users')) {
                throw new AccessDeniedHttpException('The manage platform users permission is required.');
            }
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
