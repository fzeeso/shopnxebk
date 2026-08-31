<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Requests\CustomObjectFieldWriteRequest;
use Modules\Catalog\Http\Resources\CustomObjectFieldResource;
use Modules\Catalog\Services\CustomObjectFieldService;

final class CustomObjectFieldController extends Controller
{
    public function index(
        Request $request,
        string $type,
        CustomObjectFieldService $service,
    ): JsonResponse {
        return response()->json([
            'data' => CustomObjectFieldResource::collection($service->listFields($this->user($request), $type)),
        ]);
    }

    public function store(
        CustomObjectFieldWriteRequest $request,
        string $type,
        CustomObjectFieldService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomObjectFieldResource(
                $service->createField($this->user($request), $type, $request->validated()),
            ),
        ], 201);
    }

    public function show(Request $request, string $field, CustomObjectFieldService $service): JsonResponse
    {
        return response()->json([
            'data' => new CustomObjectFieldResource($service->showField($this->user($request), $field)),
        ]);
    }

    public function update(
        CustomObjectFieldWriteRequest $request,
        string $field,
        CustomObjectFieldService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomObjectFieldResource(
                $service->updateField($this->user($request), $field, $request->validated()),
            ),
        ]);
    }

    public function destroy(Request $request, string $field, CustomObjectFieldService $service): JsonResponse
    {
        $service->deleteField($this->user($request), $field);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
