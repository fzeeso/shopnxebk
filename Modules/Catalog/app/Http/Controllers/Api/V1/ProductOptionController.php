<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\ProductOptionWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductOptionResource;
use Modules\Catalog\Services\ProductOptionManagementService;

final class ProductOptionController extends Controller
{
    public function index(
        Request $request,
        string $product,
        ProductOptionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => ProductOptionResource::collection($service->list($this->user($request), $product)),
        ]);
    }

    public function store(
        ProductOptionWriteRequest $request,
        string $product,
        ProductOptionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductOptionResource(
                $service->create($this->user($request), $product, $request->validated()),
            ),
        ], 201);
    }

    public function show(
        Request $request,
        string $product,
        string $option,
        ProductOptionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductOptionResource(
                $service->show($this->user($request), $product, $option),
            ),
        ]);
    }

    public function update(
        ProductOptionWriteRequest $request,
        string $product,
        string $option,
        ProductOptionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductOptionResource(
                $service->update($this->user($request), $product, $option, $request->validated()),
            ),
        ]);
    }

    public function destroy(
        Request $request,
        string $product,
        string $option,
        ProductOptionManagementService $service,
    ): JsonResponse {
        $service->delete($this->user($request), $product, $option);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
