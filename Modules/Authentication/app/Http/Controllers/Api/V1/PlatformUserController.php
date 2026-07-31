<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Http\Requests\CreatePlatformUserRequest;
use Modules\Authentication\Http\Requests\UpdatePlatformUserRequest;
use Modules\Authentication\Http\Resources\ManagedUserResource;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\PlatformUserAdminService;

final class PlatformUserController extends Controller
{
    public function index(PaginatedIndexRequest $request, PlatformUserAdminService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return ManagedUserResource::collection($service->list($actor, $request->perPage()))->response();
    }

    public function store(CreatePlatformUserRequest $request, PlatformUserAdminService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = $service->create($actor, $request->validated());

        return response()->json(['data' => new ManagedUserResource($user)], 201);
    }

    public function show(Request $request, string $user, PlatformUserAdminService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(['data' => new ManagedUserResource($service->view($actor, $user))]);
    }

    public function update(
        UpdatePlatformUserRequest $request,
        string $user,
        PlatformUserAdminService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => new ManagedUserResource($service->update($actor, $user, $request->validated())),
        ]);
    }

    public function roles(Request $request, PlatformUserAdminService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(['data' => $service->roles($actor)]);
    }
}
