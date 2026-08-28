<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\ProductWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\ProductManagementService;
use Modules\Catalog\Support\ProductInputMapper;

final class ProductController extends Controller
{
    public function index(ProductIndexRequest $request, ProductManagementService $service): JsonResponse
    {
        $data = $request->validated();
        $filter = [];
        foreach ([
            'search' => 'search',
            'locale' => 'locale',
            'sku' => 'sku',
            'status' => 'status',
            'fulfillment_type' => 'fulfillmentType',
            'condition' => 'condition',
            'is_featured' => 'isFeatured',
            'brand_id' => 'brandId',
            'category_id' => 'categoryId',
        ] as $rest => $internal) {
            if (array_key_exists($rest, $data)) {
                $filter[$internal] = $data[$rest];
            }
        }
        $sortBy = match ($data['sort_by'] ?? 'created_at') {
            'created_at' => 'createdAt',
            'updated_at' => 'updatedAt',
            'published_at' => 'publishedAt',
            'sort_order' => 'sortOrder',
            default => $data['sort_by'] ?? 'createdAt',
        };

        return ProductResource::collection($service->list($this->user($request), [
            'page' => $data['page'] ?? 1,
            'perPage' => $data['per_page'] ?? 25,
            'filter' => $filter,
            'sortBy' => $sortBy,
            'sortDirection' => $data['sort_direction'] ?? 'desc',
        ], true))->response();
    }

    public function store(ProductWriteRequest $request, ProductManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource(
                $service->create($this->user($request), ProductInputMapper::fromRest($request->validated())),
            ),
        ], 201);
    }

    public function show(Request $request, string $product, ProductManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource($service->show($this->user($request), $product, true)),
        ]);
    }

    public function update(
        ProductWriteRequest $request,
        string $product,
        ProductManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductResource(
                $service->update(
                    $this->user($request),
                    $product,
                    ProductInputMapper::fromRest($request->validated()),
                ),
            ),
        ]);
    }

    public function destroy(Request $request, string $product, ProductManagementService $service): JsonResponse
    {
        $service->delete($this->user($request), $product);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
