<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Http\Requests\CreatePlatformStoreRequest;
use Modules\Stores\Http\Requests\ListPlatformStoresRequest;
use Modules\Stores\Http\Requests\UpdatePlatformStoreRequest;
use Modules\Stores\Http\Requests\ViewPlatformStoreRequest;
use Modules\Stores\Http\Resources\PlatformStoreListResource;
use Modules\Stores\Http\Resources\StoreResource;
use Modules\Stores\Services\PlatformStoreAdminService;

final class PlatformStoreController extends Controller
{
    public function index(ListPlatformStoresRequest $request, PlatformStoreAdminService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return PlatformStoreListResource::collection($service->paginate($actor, $request->validated()))->response();
    }

    public function store(CreatePlatformStoreRequest $request, PlatformStoreAdminService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => new StoreResource($service->create($actor, $request->validated())),
        ], 201);
    }

    public function show(
        ViewPlatformStoreRequest $request,
        string $store,
        PlatformStoreAdminService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(['data' => new StoreResource($service->view($actor, $store))]);
    }

    public function update(
        UpdatePlatformStoreRequest $request,
        string $store,
        PlatformStoreAdminService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => new StoreResource($service->update($actor, $store, $request->validated())),
        ]);
    }
}
