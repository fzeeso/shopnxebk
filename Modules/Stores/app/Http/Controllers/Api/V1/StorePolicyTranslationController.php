<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;
use Modules\Stores\Http\Requests\UpsertStorePolicyTranslationRequest;
use Modules\Stores\Http\Resources\StorePolicyTranslationResource;
use Modules\Stores\Models\StorePolicy;
use Modules\Stores\Services\StorePolicyTranslationService;

final class StorePolicyTranslationController extends Controller
{
    public function upsert(
        UpsertStorePolicyTranslationRequest $request,
        StorePolicy $storePolicy,
        Language $language,
        StorePolicyTranslationService $service,
    ): JsonResponse {
        return response()->json(['data' => new StorePolicyTranslationResource(
            $service->upsert($this->user($request), $storePolicy, $language, $request->validated()),
        )]);
    }

    public function destroy(
        Request $request,
        StorePolicy $storePolicy,
        Language $language,
        StorePolicyTranslationService $service,
    ): JsonResponse {
        $service->delete($this->user($request), $storePolicy, $language);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
