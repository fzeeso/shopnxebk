<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Http\Requests\CreatePlatformStoreDomainRequest;
use Modules\Stores\Http\Requests\UpdatePlatformStoreDomainRequest;
use Modules\Stores\Http\Requests\ViewPlatformStoreRequest;
use Modules\Stores\Http\Resources\StoreDomainResource;
use Modules\Stores\Services\PlatformStoreDomainService;

final class PlatformStoreDomainController extends Controller
{
    public function index(
        ViewPlatformStoreRequest $request,
        string $store,
        PlatformStoreDomainService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => StoreDomainResource::collection($service->list($actor, $store)),
        ]);
    }

    public function store(
        CreatePlatformStoreDomainRequest $request,
        string $store,
        PlatformStoreDomainService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => new StoreDomainResource(
                $service->create($actor, $store, $request->validated()),
            ),
        ], 201);
    }

    public function update(
        UpdatePlatformStoreDomainRequest $request,
        string $store,
        string $domain,
        PlatformStoreDomainService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => new StoreDomainResource(
                $service->update($actor, $store, $domain, $request->validated()),
            ),
        ]);
    }
}
