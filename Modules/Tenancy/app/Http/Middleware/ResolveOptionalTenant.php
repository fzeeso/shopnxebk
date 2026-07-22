<?php

declare(strict_types=1);

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveOptionalTenant
{
    public function __construct(private ResolveTenant $resolve, private EnsureTenantMembership $membership) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('X-Tenant-ID')) {
            return $next($request);
        }

        return $this->resolve->handle($request, fn (Request $resolved): Response => $this->membership->handle($resolved, $next));
    }
}
