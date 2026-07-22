<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

final readonly class ClearRequestContext
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
            Tenant::forgetCurrent();
            setPermissionsTeamId(null);
            Auth::forgetGuards();
            app()->setLocale((string) config('app.locale'));
            Log::withoutContext();
        }
    }
}
