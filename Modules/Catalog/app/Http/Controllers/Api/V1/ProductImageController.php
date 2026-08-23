<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use App\Http\Requests\ProductImageWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ProductImageResource;
use Modules\Catalog\Services\ProductImageManagementService;

final class ProductImageController extends Controller
{
    public function index(
        PaginatedIndexRequest $request,
        string $product,
        ProductImageManagementService $service,
    ): JsonResponse {
        return ProductImageResource::collection($service->list(
            $this->user($request),
            $product,
            (int) $request->validated('page', 1),
            $request->perPage(),
        ))->response();
    }

    public function store(
        ProductImageWriteRequest $request,
        string $product,
        ProductImageManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductImageResource($service->create(
                $this->user($request),
                $product,
                $this->serviceInput($request->validated()),
            )),
        ], 201);
    }

    public function show(
        Request $request,
        string $product,
        string $image,
        ProductImageManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductImageResource($service->show($this->user($request), $product, $image)),
        ]);
    }

    public function update(
        ProductImageWriteRequest $request,
        string $product,
        string $image,
        ProductImageManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new ProductImageResource($service->update(
                $this->user($request),
                $product,
                $image,
                $this->serviceInput($request->validated()),
            )),
        ]);
    }

    public function destroy(
        Request $request,
        string $product,
        string $image,
        ProductImageManagementService $service,
    ): JsonResponse {
        $service->delete($this->user($request), $product, $image);

        return response()->json(null, 204);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function serviceInput(array $data): array
    {
        $input = [];
        foreach ([
            'variant_id' => 'variantId',
            'url' => 'url',
            'width' => 'width',
            'height' => 'height',
            'position' => 'position',
        ] as $rest => $internal) {
            if (array_key_exists($rest, $data)) {
                $input[$internal] = $data[$rest];
            }
        }
        if (array_key_exists('translations', $data)) {
            $input['translations'] = array_map(static fn (array $translation): array => [
                'locale' => $translation['locale'],
                ...array_key_exists('alt_text', $translation) ? ['altText' => $translation['alt_text']] : [],
                ...array_key_exists('lock_it', $translation) ? ['lockIt' => $translation['lock_it']] : [],
            ], $data['translations']);
        }

        return $input;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
