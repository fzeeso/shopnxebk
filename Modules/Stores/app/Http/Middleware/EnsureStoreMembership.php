<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Enums\MembershipStatus;
use Modules\Stores\Models\StoreMembership;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class EnsureStoreMembership
{
    public function __construct(private StoreContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? Auth::guard('sanctum')->user();
        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }
        if (! $user->isStoreUser()) {
            throw new AccessDeniedHttpException('Store-scoped account required.');
        }

        $store = $this->context->require();
        $membership = StoreMembership::query()
            ->where('store_id', $store->getKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', MembershipStatus::Active->value)
            ->first();

        if ($membership === null) {
            throw new AccessDeniedHttpException('Active store membership is required.');
        }

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken && $token->store_id !== null && $token->store_id !== $store->getKey()) {
            throw new AccessDeniedHttpException('Token is not valid for the selected store.');
        }

        setPermissionsTeamId($store->getKey());
        Log::withContext([
            'store_id' => $store->getKey(),
            'store_public_id' => $store->public_id,
            'user_id' => $user->getAuthIdentifier(),
        ]);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('store_membership', $membership);

        return $next($request);
    }
}
