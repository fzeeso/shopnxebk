<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Http\Requests\CreateStorePolicyRequest;
use Modules\Stores\Http\Requests\UpdateStorePolicyRequest;
use Modules\Stores\Http\Resources\StorePolicyResource;
use Modules\Stores\Models\StorePolicy;
use Modules\Stores\Services\StorePolicyService;

final class StorePolicyController extends Controller
{
    public function index(Request $request, StorePolicyService $service): JsonResponse
    {
        return response()->json(['data' => StorePolicyResource::collection($service->list($this->user($request)))]);
    }

    public function store(CreateStorePolicyRequest $request, StorePolicyService $service): JsonResponse
    {
        return response()->json(['data' => new StorePolicyResource(
            $service->create($this->user($request), $request->validated()),
        )], 201);
    }

    public function show(Request $request, StorePolicy $storePolicy, StorePolicyService $service): JsonResponse
    {
        return response()->json(['data' => new StorePolicyResource(
            $service->show($this->user($request), $storePolicy),
        )]);
    }

    public function update(UpdateStorePolicyRequest $request, StorePolicy $storePolicy, StorePolicyService $service): JsonResponse
    {
        return response()->json(['data' => new StorePolicyResource(
            $service->update($this->user($request), $storePolicy, $request->validated()),
        )]);
    }

    public function publish(Request $request, StorePolicy $storePolicy, StorePolicyService $service): JsonResponse
    {
        return response()->json(['data' => new StorePolicyResource(
            $service->publish($this->user($request), $storePolicy),
        )]);
    }

    public function unpublish(Request $request, StorePolicy $storePolicy, StorePolicyService $service): JsonResponse
    {
        return response()->json(['data' => new StorePolicyResource(
            $service->unpublish($this->user($request), $storePolicy),
        )]);
    }

    public function destroy(Request $request, StorePolicy $storePolicy, StorePolicyService $service): JsonResponse
    {
        $service->delete($this->user($request), $storePolicy);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
