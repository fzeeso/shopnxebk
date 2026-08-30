<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Customers\Http\Requests\CustomerGroupWriteRequest;
use Modules\Customers\Http\Requests\ListCustomerGroupsRequest;
use Modules\Customers\Http\Requests\ReplaceCustomerGroupCategoriesRequest;
use Modules\Customers\Http\Resources\CustomerGroupResource;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Services\CustomerGroupManagementService;

final class CustomerGroupController extends Controller
{
    public function index(ListCustomerGroupsRequest $request, CustomerGroupManagementService $service): JsonResponse
    {
        return CustomerGroupResource::collection($service->list($this->user($request), $request->validated()))->response();
    }

    public function store(CustomerGroupWriteRequest $request, CustomerGroupManagementService $service): JsonResponse
    {
        return response()->json(['data' => new CustomerGroupResource(
            $service->create($this->user($request), $request->validated()),
        )], 201);
    }

    public function show(
        Request $request,
        CustomerGroup $customerGroup,
        CustomerGroupManagementService $service,
    ): JsonResponse {
        return response()->json(['data' => new CustomerGroupResource(
            $service->show($this->user($request), $customerGroup),
        )]);
    }

    public function update(
        CustomerGroupWriteRequest $request,
        CustomerGroup $customerGroup,
        CustomerGroupManagementService $service,
    ): JsonResponse {
        return response()->json(['data' => new CustomerGroupResource(
            $service->update($this->user($request), $customerGroup, $request->validated()),
        )]);
    }

    public function replaceCategories(
        ReplaceCustomerGroupCategoriesRequest $request,
        CustomerGroup $customerGroup,
        CustomerGroupManagementService $service,
    ): JsonResponse {
        /** @var list<string> $categoryIds */
        $categoryIds = $request->validated('category_ids');

        return response()->json(['data' => new CustomerGroupResource(
            $service->replaceCategories($this->user($request), $customerGroup, $categoryIds),
        )]);
    }

    public function destroy(
        Request $request,
        CustomerGroup $customerGroup,
        CustomerGroupManagementService $service,
    ): JsonResponse {
        $service->delete($this->user($request), $customerGroup);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
