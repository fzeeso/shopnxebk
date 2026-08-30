<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Customers\Http\Requests\CreateCustomerCreditRequest;
use Modules\Customers\Http\Resources\CustomerCreditResource;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerCreditService;

final class CustomerCreditController extends Controller
{
    public function index(
        PaginatedIndexRequest $request,
        Customer $customer,
        CustomerCreditService $service,
    ): JsonResponse {
        return CustomerCreditResource::collection(
            $service->list($this->user($request), $customer, $request->perPage()),
        )->response();
    }

    public function store(
        CreateCustomerCreditRequest $request,
        Customer $customer,
        CustomerCreditService $service,
    ): JsonResponse {
        return response()->json(['data' => new CustomerCreditResource(
            $service->create($this->user($request), $customer, $request->validated()),
        )], 201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
