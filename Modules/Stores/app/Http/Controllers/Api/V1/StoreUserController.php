<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
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
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $user = $service->create($actor, $context->require(), $request->validated());

        return response()->json(['data' => new StoreUserResource($user)], 201);
    }

    public function roles(Request $request, StoreContext $context, StoreUserAdminService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(['data' => $service->roles($actor, $context->require())]);
    }
}
