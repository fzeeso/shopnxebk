<?php

declare(strict_types=1);

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\TenantFinder\HeaderTenantFinder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ResolveTenant
{
    public function __construct(private HeaderTenantFinder $finder, private TenantContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        if (! $request->hasHeader('X-Tenant-ID')) {
            if ($mode === 'optional') {
                return $next($request);
            }
            throw new BadRequestHttpException('X-Tenant-ID is required for this operation.');
        }

        $tenant = $this->finder->findForRequest($request);
        if (! $tenant instanceof Tenant) {
            throw new NotFoundHttpException('Tenant not found.');
        }

        $tenant->makeCurrent();
        $this->context->set($tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
