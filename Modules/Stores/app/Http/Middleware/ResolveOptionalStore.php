<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveOptionalStore
{
    public function __construct(private ResolveStore $resolve, private EnsureStoreMembership $membership) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('X-Store-ID')) {
            return $next($request);
        }

        return $this->resolve->handle($request, fn (Request $resolved): Response => $this->membership->handle($resolved, $next));
    }
}
