<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductModifierAssignmentResource;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Services\CatalogAccessService;
use Modules\Catalog\Services\ProductModifierAssignmentService;
use Modules\Catalog\Services\ProductModifierResolver;
use Modules\Stores\Contracts\StoreContext;

final class ProductModifierController extends Controller
{
    public function index(Request $request, string $product, ProductModifierAssignmentService $service): JsonResponse
    {
        return response()->json(['data' => ProductModifierAssignmentResource::collection($service->list($this->user($request), $product))]);
    }

    public function store(Request $request, string $product, ProductModifierAssignmentService $service): JsonResponse
    {
        return response()->json(['data' => new ProductModifierAssignmentResource($service->assign($this->user($request), $product, $request->all()))], 201);
    }

    public function update(Request $request, string $product, string $assignment, ProductModifierAssignmentService $service): JsonResponse
    {
        return response()->json(['data' => new ProductModifierAssignmentResource($service->update($this->user($request), $product, $assignment, $request->all()))]);
    }

    public function destroy(Request $request, string $product, string $assignment, ProductModifierAssignmentService $service): JsonResponse
    {
        $service->remove($this->user($request), $product, $assignment);

        return response()->json(null, 204);
    }

    public function reorder(Request $request, string $product, ProductModifierAssignmentService $service): JsonResponse
    {
        return response()->json(['data' => ProductModifierAssignmentResource::collection($service->reorder($this->user($request), $product, (array) $request->input('items', [])))]);
    }

    public function resolved(
        Request $request,
        string $product,
        StoreContext $context,
        CatalogAccessService $access,
        ProductModifierResolver $resolver,
    ): JsonResponse {
        $data = $request->validate([
            'locale' => ['sometimes', 'string', 'max:20'],
            'currency' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
        ]);
        $store = $context->require();
        $access->ensureCanView($this->user($request), $store);
        $model = Product::query()->where('store_id', $store->getKey())->where('public_id', $product)->firstOrFail();

        return response()->json(['data' => $resolver->resolve(
            $store,
            $model,
            (string) ($data['locale'] ?? $store->language_code),
            strtoupper((string) ($data['currency'] ?? $store->currency_code)),
        )]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
