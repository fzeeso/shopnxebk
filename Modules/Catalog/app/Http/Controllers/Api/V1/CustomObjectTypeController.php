<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Requests\CustomObjectIndexRequest;
use Modules\Catalog\Http\Requests\CustomObjectTypeWriteRequest;
use Modules\Catalog\Http\Resources\CustomObjectTypeResource;
use Modules\Catalog\Services\CustomObjectTypeService;

final class CustomObjectTypeController extends Controller
{
    public function index(CustomObjectIndexRequest $request, CustomObjectTypeService $service): JsonResponse
    {
        return CustomObjectTypeResource::collection(
            $service->listTypes($this->user($request), $request->validated()),
        )->response();
    }

    public function store(CustomObjectTypeWriteRequest $request, CustomObjectTypeService $service): JsonResponse
    {
        return response()->json([
            'data' => new CustomObjectTypeResource($service->createType($this->user($request), $request->validated())),
        ], 201);
    }

    public function show(Request $request, string $type, CustomObjectTypeService $service): JsonResponse
    {
        return response()->json([
            'data' => new CustomObjectTypeResource($service->showType($this->user($request), $type)),
        ]);
    }

    public function update(
        CustomObjectTypeWriteRequest $request,
        string $type,
        CustomObjectTypeService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomObjectTypeResource(
                $service->updateType($this->user($request), $type, $request->validated()),
            ),
        ]);
    }

    public function destroy(Request $request, string $type, CustomObjectTypeService $service): JsonResponse
    {
        $service->deleteType($this->user($request), $type);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
