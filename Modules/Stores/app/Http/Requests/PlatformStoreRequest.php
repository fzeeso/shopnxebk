<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\Models\User;

abstract class PlatformStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User || ! $user->isPlatformUser()) {
            return false;
        }

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            return $user->can('manage stores');
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
