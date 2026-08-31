<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Requests\CustomObjectReferenceRequest;
use Modules\Catalog\Http\Resources\CustomObjectReferenceResource;
use Modules\Catalog\Services\CustomObjectReferenceService;

final class CustomObjectReferenceController extends Controller
{
    public function index(
        CustomObjectReferenceRequest $request,
        CustomObjectReferenceService $service,
    ): JsonResponse {
        $data = $request->validated();

        return response()->json(['data' => CustomObjectReferenceResource::collection($service->list(
            $this->user($request),
            $data['source_type'],
            $data['source_id'],
            $data['definition_id'] ?? null,
        ))]);
    }

    public function replace(
        CustomObjectReferenceRequest $request,
        string $definition,
        CustomObjectReferenceService $service,
    ): JsonResponse {
        $data = $request->validated();

        return response()->json(['data' => CustomObjectReferenceResource::collection($service->replace(
            $this->user($request),
            $data['source_type'],
            $data['source_id'],
            $definition,
            $data['entry_ids'],
        ))]);
    }

    public function clear(
        CustomObjectReferenceRequest $request,
        string $definition,
        CustomObjectReferenceService $service,
    ): JsonResponse {
        $data = $request->validated();
        $service->clear($this->user($request), $data['source_type'], $data['source_id'], $definition);

        return response()->json(null, 204);
    }

    public function productIndex(
        CustomObjectReferenceRequest $request,
        string $product,
        CustomObjectReferenceService $service,
    ): JsonResponse {
        return response()->json(['data' => CustomObjectReferenceResource::collection($service->list(
            $this->user($request),
            'product',
            $product,
            $request->validated('definition_id'),
        ))]);
    }

    public function productReplace(
        CustomObjectReferenceRequest $request,
        string $product,
        string $definition,
        CustomObjectReferenceService $service,
    ): JsonResponse {
        return response()->json(['data' => CustomObjectReferenceResource::collection($service->replace(
            $this->user($request),
            'product',
            $product,
            $definition,
            $request->validated('entry_ids'),
        ))]);
    }

    public function productClear(
        CustomObjectReferenceRequest $request,
        string $product,
        string $definition,
        CustomObjectReferenceService $service,
    ): JsonResponse {
        $service->clear($this->user($request), 'product', $product, $definition);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
