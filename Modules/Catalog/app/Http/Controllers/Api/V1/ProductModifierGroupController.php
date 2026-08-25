<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductModifierGroupResource;
use Modules\Catalog\Services\ProductModifierAssignmentService;

final class ProductModifierGroupController extends Controller
{
    public function index(Request $request, string $product, ProductModifierAssignmentService $service): JsonResponse
    {
        return response()->json(['data' => ProductModifierGroupResource::collection($service->groups($this->user($request), $product))]);
    }

    public function store(Request $request, string $product, ProductModifierAssignmentService $service): JsonResponse
    {
        return response()->json(['data' => new ProductModifierGroupResource($service->createGroup($this->user($request), $product, $request->all()))], 201);
    }

    public function show(Request $request, string $product, string $group, ProductModifierAssignmentService $service): JsonResponse
    {
        return response()->json(['data' => new ProductModifierGroupResource($service->showGroup($this->user($request), $product, $group))]);
    }

    public function update(Request $request, string $product, string $group, ProductModifierAssignmentService $service): JsonResponse
    {
        return response()->json(['data' => new ProductModifierGroupResource($service->updateGroup($this->user($request), $product, $group, $request->all()))]);
    }

    public function destroy(Request $request, string $product, string $group, ProductModifierAssignmentService $service): JsonResponse
    {
        $service->deleteGroup($this->user($request), $product, $group);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
