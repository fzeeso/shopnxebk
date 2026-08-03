<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Http\Requests\UpdatePlatformStoreLocaleSettingsRequest;
use Modules\Stores\Http\Requests\ViewPlatformStoreRequest;
use Modules\Stores\Http\Resources\StoreLocaleSettingsResource;
use Modules\Stores\Services\PlatformStoreLocaleSettingsService;

final class PlatformStoreLocaleSettingsController extends Controller
{
    public function show(
        ViewPlatformStoreRequest $request,
        string $store,
        PlatformStoreLocaleSettingsService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => new StoreLocaleSettingsResource($service->view($actor, $store)),
        ]);
    }

    public function update(
        UpdatePlatformStoreLocaleSettingsRequest $request,
        string $store,
        PlatformStoreLocaleSettingsService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => new StoreLocaleSettingsResource(
                $service->update($actor, $store, $request->validated()),
            ),
        ]);
    }
}
