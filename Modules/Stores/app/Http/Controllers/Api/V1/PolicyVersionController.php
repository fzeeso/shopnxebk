<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Http\Resources\PolicyVersionResource;
use Modules\Stores\Models\PolicyVersion;
use Modules\Stores\Models\StorePolicy;
use Modules\Stores\Services\PolicyVersionService;

final class PolicyVersionController extends Controller
{
    public function index(Request $request, StorePolicy $storePolicy, PolicyVersionService $service): JsonResponse
    {
        return response()->json(['data' => PolicyVersionResource::collection(
            $service->list($this->user($request), $storePolicy),
        )]);
    }

    public function restore(
        Request $request,
        StorePolicy $storePolicy,
        PolicyVersion $policyVersion,
        PolicyVersionService $service,
    ): JsonResponse {
        return response()->json(['data' => new PolicyVersionResource(
            $service->restore($this->user($request), $storePolicy, $policyVersion),
        )], 201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
