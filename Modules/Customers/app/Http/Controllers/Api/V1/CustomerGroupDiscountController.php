<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Customers\Http\Requests\CustomerGroupDiscountWriteRequest;
use Modules\Customers\Http\Resources\CustomerGroupDiscountResource;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Models\CustomerGroupDiscount;
use Modules\Customers\Services\CustomerGroupManagementService;

final class CustomerGroupDiscountController extends Controller
{
    public function store(
        CustomerGroupDiscountWriteRequest $request,
        CustomerGroup $customerGroup,
        CustomerGroupManagementService $service,
    ): JsonResponse {
        return response()->json(['data' => new CustomerGroupDiscountResource(
            $service->createDiscount($this->user($request), $customerGroup, $request->validated()),
        )], 201);
    }

    public function update(
        CustomerGroupDiscountWriteRequest $request,
        CustomerGroup $customerGroup,
        CustomerGroupDiscount $discount,
        CustomerGroupManagementService $service,
    ): JsonResponse {
        return response()->json(['data' => new CustomerGroupDiscountResource(
            $service->updateDiscount(
                $this->user($request),
                $customerGroup,
                $discount,
                $request->validated(),
            ),
        )]);
    }

    public function destroy(
        Request $request,
        CustomerGroup $customerGroup,
        CustomerGroupDiscount $discount,
        CustomerGroupManagementService $service,
    ): JsonResponse {
        $service->deleteDiscount($this->user($request), $customerGroup, $discount);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
