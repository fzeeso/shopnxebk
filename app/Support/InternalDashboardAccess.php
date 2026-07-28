<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Modules\Authentication\Models\User;

final class InternalDashboardAccess
{
    public static function allows(Request $request, ?User $user): bool
    {
        if (! (bool) config('observability.internal_dashboards_enabled') || ! $user?->isPlatformSuperAdmin()) {
            return false;
        }

        $allowList = config('observability.internal_dashboard_ips', []);

        return $allowList === [] || in_array($request->ip(), $allowList, true);
    }
}
