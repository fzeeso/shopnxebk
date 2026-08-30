<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;
use Modules\Stores\Http\Requests\UpsertPageTranslationRequest;
use Modules\Stores\Http\Resources\PageTranslationResource;
use Modules\Stores\Models\Page;
use Modules\Stores\Services\PageManagementService;

final class PageTranslationController extends Controller
{
    public function upsert(
        UpsertPageTranslationRequest $request,
        Page $page,
        Language $language,
        PageManagementService $service,
    ): JsonResponse {
        return response()->json(['data' => new PageTranslationResource(
            $service->upsertTranslation(
                $this->user($request),
                $page,
                $language,
                $request->validated(),
            ),
        )]);
    }

    public function destroy(
        Request $request,
        Page $page,
        Language $language,
        PageManagementService $service,
    ): JsonResponse {
        $service->deleteTranslation($this->user($request), $page, $language);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
