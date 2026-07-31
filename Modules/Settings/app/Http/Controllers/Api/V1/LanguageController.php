<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Settings\Http\Requests\CreateLanguageRequest;
use Modules\Settings\Http\Requests\UpdateLanguageRequest;
use Modules\Settings\Http\Resources\LanguageResource;
use Modules\Settings\Models\Language;
use Modules\Settings\Services\LanguageCatalogService;

final class LanguageController extends Controller
{
    public function index(PaginatedIndexRequest $request, LanguageCatalogService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return LanguageResource::collection(
            $service->listPlatform($user, $request->perPage()),
        )->response();
    }

    public function store(CreateLanguageRequest $request, LanguageCatalogService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $language = $service->createPlatform($user, $request->validated());

        return response()->json(['data' => new LanguageResource($language)], 201);
    }

    public function update(
        UpdateLanguageRequest $request,
        string $language,
        LanguageCatalogService $service,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $model = Language::query()->where('public_id', $language)->firstOrFail();
        $updated = $service->updatePlatform($user, $model, $request->validated());

        return response()->json(['data' => new LanguageResource($updated)]);
    }
}
