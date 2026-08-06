<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Http\Requests\CreatePolicyTypeRequest;
use Modules\Stores\Http\Requests\UpdatePolicyTypeRequest;
use Modules\Stores\Http\Resources\PolicyTypeResource;
use Modules\Stores\Models\PolicyType;
use Modules\Stores\Services\PolicyTypeCatalogService;

final class PolicyTypeController extends Controller
{
    public function platformIndex(PaginatedIndexRequest $request, PolicyTypeCatalogService $service): JsonResponse
    {
        return PolicyTypeResource::collection($service->listPlatform($this->user($request), $request->perPage()))->response();
    }

    public function storeIndex(Request $request, StoreContext $context, PolicyTypeCatalogService $service): JsonResponse
    {
        return response()->json(['data' => PolicyTypeResource::collection(
            $service->listForStore($this->user($request), $context->require()),
        )]);
    }

    public function store(CreatePolicyTypeRequest $request, PolicyTypeCatalogService $service): JsonResponse
    {
        return response()->json(['data' => new PolicyTypeResource(
            $service->createPlatform($this->user($request), $request->validated()),
        )], 201);
    }

    public function update(UpdatePolicyTypeRequest $request, PolicyType $policyType, PolicyTypeCatalogService $service): JsonResponse
    {
        return response()->json(['data' => new PolicyTypeResource(
            $service->updatePlatform($this->user($request), $policyType, $request->validated()),
        )]);
    }

    public function destroy(Request $request, PolicyType $policyType, PolicyTypeCatalogService $service): JsonResponse
    {
        $service->deletePlatform($this->user($request), $policyType);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
