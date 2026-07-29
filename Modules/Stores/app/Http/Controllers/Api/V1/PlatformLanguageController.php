<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Http\Requests\CreateLanguageRequest;
use Modules\Stores\Http\Resources\LanguageResource;
use Modules\Stores\Services\LanguageCatalogService;

final class PlatformLanguageController extends Controller
{
    public function index(Request $request, LanguageCatalogService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => LanguageResource::collection($service->listPlatform($user)),
        ]);
    }

    public function store(CreateLanguageRequest $request, LanguageCatalogService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $language = $service->createPlatform($user, $request->validated());

        return response()->json(['data' => new LanguageResource($language)], 201);
    }
}
