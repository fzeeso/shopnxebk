<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use Modules\Authentication\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class PlatformSettingsAccessService
{
    public function ensureCanView(User $user): void
    {
        if (! $user->isPlatformUser()) {
            throw new AccessDeniedHttpException('Platform-scoped account required.');
        }
    }

    public function ensureCanManage(User $user): void
    {
        $this->ensureCanView($user);

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            if (! $user->can('manage platform settings')) {
                throw new AccessDeniedHttpException('The manage platform settings permission is required.');
            }
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
