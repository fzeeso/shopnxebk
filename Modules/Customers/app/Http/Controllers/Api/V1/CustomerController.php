<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Customers\Http\Requests\CustomerWriteRequest;
use Modules\Customers\Http\Requests\ListCustomersRequest;
use Modules\Customers\Http\Resources\CustomerResource;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerManagementService;

final class CustomerController extends Controller
{
    public function index(ListCustomersRequest $request, CustomerManagementService $service): JsonResponse
    {
        return CustomerResource::collection($service->list($this->user($request), $request->validated()))->response();
    }

    public function store(CustomerWriteRequest $request, CustomerManagementService $service): JsonResponse
    {
        return response()->json(['data' => new CustomerResource(
            $service->create($this->user($request), $request->validated()),
        )], 201);
    }

    public function show(Request $request, Customer $customer, CustomerManagementService $service): JsonResponse
    {
        return response()->json(['data' => new CustomerResource(
            $service->show($this->user($request), $customer),
        )]);
    }

    public function update(
        CustomerWriteRequest $request,
        Customer $customer,
        CustomerManagementService $service,
    ): JsonResponse {
        return response()->json(['data' => new CustomerResource(
            $service->update($this->user($request), $customer, $request->validated()),
        )]);
    }

    public function destroy(Request $request, Customer $customer, CustomerManagementService $service): JsonResponse
    {
        $service->delete($this->user($request), $customer);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
