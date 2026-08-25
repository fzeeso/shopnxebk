<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\ProductOptionValueWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductOptionValueResource;
use Modules\Catalog\Services\ProductOptionManagementService;

final class ProductOptionValueController extends Controller
{
    public function index(
        Request $request,
        string $product,
        string $option,
        ProductOptionManagementService $service,
    ): JsonResponse {
        $values = $service->show($this->user($request), $product, $option)->values;

        return response()->json(['data' => ProductOptionValueResource::collection($values)]);
    }

    public function store(
        ProductOptionValueWriteRequest $request,
        string $product,
        string $option,
        ProductOptionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductOptionValueResource(
                $service->createValue($this->user($request), $product, $option, $request->validated()),
            ),
        ], 201);
    }

    public function show(
        Request $request,
        string $product,
        string $option,
        string $value,
        ProductOptionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductOptionValueResource(
                $service->showValue($this->user($request), $product, $option, $value),
            ),
        ]);
    }

    public function update(
        ProductOptionValueWriteRequest $request,
        string $product,
        string $option,
        string $value,
        ProductOptionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductOptionValueResource(
                $service->updateValue(
                    $this->user($request),
                    $product,
                    $option,
                    $value,
                    $request->validated(),
                ),
            ),
        ]);
    }

    public function destroy(
        Request $request,
        string $product,
        string $option,
        string $value,
        ProductOptionManagementService $service,
    ): JsonResponse {
        $service->deleteValue($this->user($request), $product, $option, $value);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
