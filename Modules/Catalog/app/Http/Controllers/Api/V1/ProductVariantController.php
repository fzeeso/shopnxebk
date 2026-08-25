<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use App\Http\Requests\ProductVariantWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductVariantResource;
use Modules\Catalog\Services\ProductVariantManagementService;

final class ProductVariantController extends Controller
{
    public function index(
        PaginatedIndexRequest $request,
        string $product,
        ProductVariantManagementService $service,
    ): JsonResponse {
        return ProductVariantResource::collection($service->list(
            $this->user($request),
            $product,
            (int) $request->validated('page', 1),
            $request->perPage(),
        ))->response();
    }

    public function store(
        ProductVariantWriteRequest $request,
        string $product,
        ProductVariantManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductVariantResource(
                $service->create($this->user($request), $product, $request->validated()),
            ),
        ], 201);
    }

    public function show(
        Request $request,
        string $product,
        string $variant,
        ProductVariantManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductVariantResource(
                $service->show($this->user($request), $product, $variant),
            ),
        ]);
    }

    public function update(
        ProductVariantWriteRequest $request,
        string $product,
        string $variant,
        ProductVariantManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductVariantResource(
                $service->update($this->user($request), $product, $variant, $request->validated()),
            ),
        ]);
    }

    public function destroy(
        Request $request,
        string $product,
        string $variant,
        ProductVariantManagementService $service,
    ): JsonResponse {
        $service->delete($this->user($request), $product, $variant);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
