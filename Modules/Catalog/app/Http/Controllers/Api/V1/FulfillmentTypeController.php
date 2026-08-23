<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Requests\FulfillmentTypeWriteRequest;
use Modules\Catalog\Http\Resources\FulfillmentTypeResource;
use Modules\Catalog\Services\FulfillmentTypeManagementService;

final class FulfillmentTypeController extends Controller
{
    public function index(Request $request, FulfillmentTypeManagementService $service): AnonymousResourceCollection
    {
        return FulfillmentTypeResource::collection($service->listPlatform($this->user($request)));
    }

    public function storeIndex(Request $request, FulfillmentTypeManagementService $service): AnonymousResourceCollection
    {
        return FulfillmentTypeResource::collection($service->listStore($this->user($request)));
    }

    public function store(
        FulfillmentTypeWriteRequest $request,
        FulfillmentTypeManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new FulfillmentTypeResource(
                $service->createPlatform($this->user($request), $request->validated()),
            ),
        ], 201);
    }

    public function show(
        Request $request,
        string $fulfillmentType,
        FulfillmentTypeManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new FulfillmentTypeResource(
                $service->showPlatform($this->user($request), $fulfillmentType),
            ),
        ]);
    }

    public function update(
        FulfillmentTypeWriteRequest $request,
        string $fulfillmentType,
        FulfillmentTypeManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new FulfillmentTypeResource(
                $service->updatePlatform(
                    $this->user($request),
                    $fulfillmentType,
                    $request->validated(),
                ),
            ),
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
