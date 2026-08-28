<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\ProductDetailReadRequest;
use App\Http\Requests\ProductDetailWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductDetailResource;
use Modules\Catalog\Services\ProductDetailReadService;
use Modules\Catalog\Services\ProductDetailWriteService;

final class ProductDetailController extends Controller
{
    public function bootstrap(
        ProductDetailReadRequest $request,
        ProductDetailReadService $reader,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductDetailResource(
                $reader->bootstrap($this->user($request), $request->referenceLimit()),
            ),
        ]);
    }

    public function show(
        ProductDetailReadRequest $request,
        string $product,
        ProductDetailReadService $reader,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductDetailResource($reader->show(
                $this->user($request),
                $product,
                $request->sectionLimit(),
                $request->withReferenceData(),
                $request->referenceLimit(),
            )),
        ]);
    }

    public function store(
        ProductDetailWriteRequest $request,
        ProductDetailWriteService $writer,
        ProductDetailReadService $reader,
    ): JsonResponse {
        $user = $this->user($request);
        $result = $writer->create($user, $request->validated());
        $detail = $reader->show($user, (string) $result['product_id'], 100, false);

        return response()->json([
            'data' => new ProductDetailResource([...$detail, ...$result]),
        ], 201);
    }

    public function update(
        ProductDetailWriteRequest $request,
        string $product,
        ProductDetailWriteService $writer,
        ProductDetailReadService $reader,
    ): JsonResponse {
        $user = $this->user($request);
        $result = $writer->update($user, $product, $request->validated());
        $detail = $reader->show($user, $product, 100, false);

        return response()->json([
            'data' => new ProductDetailResource([...$detail, ...$result]),
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
