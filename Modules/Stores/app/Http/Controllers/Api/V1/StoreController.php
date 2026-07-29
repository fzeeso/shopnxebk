<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Http\Requests\CreateStoreRequest;
use Modules\Stores\Http\Requests\UpdateStoreProfileRequest;
use Modules\Stores\Http\Requests\UpdateStoreSettingsRequest;
use Modules\Stores\Http\Resources\StoreResource;
use Modules\Stores\Http\Resources\StoreSettingsResource;
use Modules\Stores\Services\CreateStoreService;
use Modules\Stores\Services\StoreSettingsService;
use Modules\Stores\Services\UpdateStoreProfileService;
use Modules\Stores\Services\ViewStoreService;

final class StoreController extends Controller
{
    public function store(CreateStoreRequest $request, CreateStoreService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store = $service->create($user, $request->validated());

        return response()->json(['data' => new StoreResource($store)], 201);
    }

    public function show(Request $request, StoreContext $context, ViewStoreService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => new StoreResource($service->view($user, $context->require()))]);
    }

    public function updateProfile(
        UpdateStoreProfileRequest $request,
        StoreContext $context,
        UpdateStoreProfileService $service,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $store = $service->update($user, $context->require(), $request->validated());

        return response()->json(['data' => new StoreResource($store)]);
    }

    public function settings(Request $request, StoreContext $context, StoreSettingsService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => new StoreSettingsResource($service->view($user, $context->require()))]);
    }

    public function updateSettings(
        UpdateStoreSettingsRequest $request,
        StoreContext $context,
        StoreSettingsService $service,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $store = $service->update($user, $context->require(), $request->validated());

        return response()->json(['data' => new StoreSettingsResource($store)]);
    }
}
