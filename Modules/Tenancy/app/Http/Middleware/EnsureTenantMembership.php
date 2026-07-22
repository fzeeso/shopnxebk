<?php

declare(strict_types=1);

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Tenancy\Contracts\TenantContext;
use Modules\Tenancy\Enums\MembershipStatus;
use Modules\Tenancy\Models\TenantMembership;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class EnsureTenantMembership
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? Auth::guard('sanctum')->user();
        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $tenant = $this->context->require();
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', MembershipStatus::Active->value)
            ->first();

        if ($membership === null) {
            throw new AccessDeniedHttpException('Active tenant membership is required.');
        }

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken && $token->tenant_id !== null && $token->tenant_id !== $tenant->getKey()) {
            throw new AccessDeniedHttpException('Token is not valid for the selected tenant.');
        }

        setPermissionsTeamId($tenant->getKey());
        Log::withContext([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getAuthIdentifier(),
        ]);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('tenant_membership', $membership);

        return $next($request);
    }
}
