<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use App\Support\Idempotency\IdempotencyExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Http\Requests\CreateStoreUserRequest;
use Modules\Stores\Http\Resources\StoreUserResource;
use Modules\Stores\Services\StoreUserAdminService;

final class StoreUserController extends Controller
{
    public function index(PaginatedIndexRequest $request, StoreContext $context, StoreUserAdminService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return StoreUserResource::collection(
            $service->list($actor, $context->require(), $request->perPage()),
        )->response();
    }

    public function store(
        CreateStoreUserRequest $request,
        StoreContext $context,
        StoreUserAdminService $service,
        IdempotencyExecutor $idempotency,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $store = $context->require();
        $data = $request->validated();

        return $idempotency->execute(
            request: $request,
            operation: 'api.v1.store.users.store',
            preflight: function () use ($actor, $service, $store): void {
                $service->authorizeCreation($actor, $store);
            },
            action: fn (): JsonResponse => response()->json([
                'data' => new StoreUserResource($service->create($actor, $store, $data)),
            ], 201),
        );
    }

    public function roles(Request $request, StoreContext $context, StoreUserAdminService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(['data' => $service->roles($actor, $context->require())]);
    }
}
