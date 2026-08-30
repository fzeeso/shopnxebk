<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Customers\Http\Requests\UpsertCustomerGroupTranslationRequest;
use Modules\Customers\Http\Resources\CustomerGroupTranslationResource;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Services\CustomerGroupManagementService;
use Modules\Settings\Models\Language;

final class CustomerGroupTranslationController extends Controller
{
    public function upsert(
        UpsertCustomerGroupTranslationRequest $request,
        CustomerGroup $customerGroup,
        Language $language,
        CustomerGroupManagementService $service,
    ): JsonResponse {
        return response()->json(['data' => new CustomerGroupTranslationResource(
            $service->upsertTranslation(
                $this->user($request),
                $customerGroup,
                $language,
                $request->validated(),
            ),
        )]);
    }

    public function destroy(
        Request $request,
        CustomerGroup $customerGroup,
        Language $language,
        CustomerGroupManagementService $service,
    ): JsonResponse {
        $service->deleteTranslation($this->user($request), $customerGroup, $language);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
