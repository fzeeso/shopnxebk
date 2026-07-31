<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Http\Requests\CreateMerchantRequest;
use Modules\Stores\Http\Requests\UpdateMerchantRequest;
use Modules\Stores\Http\Resources\MerchantResource;
use Modules\Stores\Services\PlatformMerchantService;

final class PlatformMerchantController extends Controller
{
    public function index(PaginatedIndexRequest $request, PlatformMerchantService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return MerchantResource::collection($service->list($actor, $request->perPage()))->response();
    }

    public function store(CreateMerchantRequest $request, PlatformMerchantService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => new MerchantResource($service->create($actor, $request->validated())),
        ], 201);
    }

    public function show(Request $request, string $merchant, PlatformMerchantService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(['data' => new MerchantResource($service->view($actor, $merchant))]);
    }

    public function update(
        UpdateMerchantRequest $request,
        string $merchant,
        PlatformMerchantService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => new MerchantResource($service->update($actor, $merchant, $request->validated())),
        ]);
    }

    public function roles(Request $request, PlatformMerchantService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(['data' => $service->roles($actor)]);
    }
}
