<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class EnsureUserScope
{
    public function handle(Request $request, Closure $next, string $requiredScope): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        $scope = AccessScope::tryFrom($requiredScope);
        if ($scope === null || $user->scope !== $scope) {
            throw new AccessDeniedHttpException(ucfirst($requiredScope).'-scoped account required.');
        }

        return $next($request);
    }
}
