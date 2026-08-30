<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Requests\CollectionIndexRequest;
use Modules\Catalog\Http\Requests\CollectionNestedIndexRequest;
use Modules\Catalog\Http\Requests\CollectionWriteRequest;
use Modules\Catalog\Http\Requests\ReplaceCollectionProductsRequest;
use Modules\Catalog\Http\Requests\ReplaceCollectionRulesRequest;
use Modules\Catalog\Http\Resources\CollectionAiJobResource;
use Modules\Catalog\Http\Resources\CollectionProductResource;
use Modules\Catalog\Http\Resources\CollectionResource;
use Modules\Catalog\Services\CollectionManagementService;

final class CollectionController extends Controller
{
    public function index(CollectionIndexRequest $request, CollectionManagementService $service): JsonResponse
    {
        return CollectionResource::collection(
            $service->list($this->user($request), $request->validated()),
        )->response();
    }

    public function store(CollectionWriteRequest $request, CollectionManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => new CollectionResource($service->create($this->user($request), $request->validated())),
        ], 201);
    }

    public function show(Request $request, string $collection, CollectionManagementService $service): JsonResponse
    {
        return response()->json([
            'data' => new CollectionResource($service->show($this->user($request), $collection)),
        ]);
    }

    public function update(
        CollectionWriteRequest $request,
        string $collection,
        CollectionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CollectionResource(
                $service->update($this->user($request), $collection, $request->validated()),
            ),
        ]);
    }

    public function destroy(Request $request, string $collection, CollectionManagementService $service): JsonResponse
    {
        $service->delete($this->user($request), $collection);

        return response()->json(null, 204);
    }

    public function replaceRules(
        ReplaceCollectionRulesRequest $request,
        string $collection,
        CollectionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CollectionResource($service->replaceRules(
                $this->user($request),
                $collection,
                $request->validated('rules'),
            )),
        ]);
    }

    public function replaceProducts(
        ReplaceCollectionProductsRequest $request,
        string $collection,
        CollectionManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CollectionResource($service->replaceManualProducts(
                $this->user($request),
                $collection,
                $request->validated('products'),
            )),
        ]);
    }

    public function refresh(Request $request, string $collection, CollectionManagementService $service): JsonResponse
    {
        $result = $service->refreshMembership($this->user($request), $collection);

        return response()->json([
            'data' => new CollectionResource($result['collection']),
            'meta' => ['matched_count' => $result['matched_count']],
        ]);
    }

    public function products(
        CollectionNestedIndexRequest $request,
        string $collection,
        CollectionManagementService $service,
    ): JsonResponse {
        return CollectionProductResource::collection($service->products(
            $this->user($request),
            $collection,
            $request->integer('per_page', 25),
        ))->response();
    }

    public function aiJobs(
        CollectionNestedIndexRequest $request,
        string $collection,
        CollectionManagementService $service,
    ): JsonResponse {
        return CollectionAiJobResource::collection($service->aiJobs(
            $this->user($request),
            $collection,
            $request->integer('per_page', 25),
        ))->response();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
