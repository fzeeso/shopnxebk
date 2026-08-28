<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\ProductCustomFieldValueWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductCustomFieldValueResource;
use Modules\Catalog\Services\CustomFieldManagementService;

final class ProductCustomFieldValueController extends Controller
{
    public function productIndex(
        Request $request,
        string $product,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return $this->listResponse($service, $request, $product);
    }

    public function productShow(
        Request $request,
        string $product,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return $this->showResponse($service, $request, $product, $definition);
    }

    public function productSet(
        ProductCustomFieldValueWriteRequest $request,
        string $product,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return $this->setResponse($service, $request, $product, $definition);
    }

    public function productDestroy(
        Request $request,
        string $product,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return $this->destroyResponse($service, $request, $product, $definition);
    }

    public function variantIndex(
        Request $request,
        string $product,
        string $variant,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return $this->listResponse($service, $request, $product, $variant);
    }

    public function variantShow(
        Request $request,
        string $product,
        string $variant,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return $this->showResponse($service, $request, $product, $definition, $variant);
    }

    public function variantSet(
        ProductCustomFieldValueWriteRequest $request,
        string $product,
        string $variant,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return $this->setResponse($service, $request, $product, $definition, $variant);
    }

    public function variantDestroy(
        Request $request,
        string $product,
        string $variant,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return $this->destroyResponse($service, $request, $product, $definition, $variant);
    }

    private function listResponse(
        CustomFieldManagementService $service,
        Request $request,
        string $product,
        ?string $variant = null,
    ): JsonResponse {
        return response()->json([
            'data' => ProductCustomFieldValueResource::collection(
                $service->listValues($this->user($request), $product, $variant),
            ),
        ]);
    }

    private function showResponse(
        CustomFieldManagementService $service,
        Request $request,
        string $product,
        string $definition,
        ?string $variant = null,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductCustomFieldValueResource(
                $service->showValue($this->user($request), $product, $definition, $variant),
            ),
        ]);
    }

    private function setResponse(
        CustomFieldManagementService $service,
        ProductCustomFieldValueWriteRequest $request,
        string $product,
        string $definition,
        ?string $variant = null,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductCustomFieldValueResource($service->setValue(
                $this->user($request),
                $product,
                $definition,
                $request->validated(),
                $variant,
            )),
        ]);
    }

    private function destroyResponse(
        CustomFieldManagementService $service,
        Request $request,
        string $product,
        string $definition,
        ?string $variant = null,
    ): JsonResponse {
        $service->deleteValue($this->user($request), $product, $definition, $variant);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
