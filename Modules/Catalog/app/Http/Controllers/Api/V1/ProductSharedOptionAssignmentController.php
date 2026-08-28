<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductSharedOptionAssignmentResource;
use Modules\Catalog\Services\SharedProductOptionService;

final class ProductSharedOptionAssignmentController extends Controller
{
    public function index(Request $request, string $product, SharedProductOptionService $service): JsonResponse
    {
        return response()->json([
            'data' => ProductSharedOptionAssignmentResource::collection(
                $service->assignments($this->user($request), $product),
            ),
        ]);
    }

    public function store(Request $request, string $product, SharedProductOptionService $service): JsonResponse
    {
        return response()->json([
            'data' => new ProductSharedOptionAssignmentResource(
                $service->assign($this->user($request), $product, $request->all()),
            ),
        ], 201);
    }

    public function destroy(
        Request $request,
        string $product,
        string $assignment,
        SharedProductOptionService $service,
    ): JsonResponse {
        $service->unassign($this->user($request), $product, $assignment);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
