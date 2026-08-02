<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Themes\Http\Requests\PlatformThemeCategoryRequest;
use Modules\Themes\Http\Requests\PlatformThemePublisherRequest;
use Modules\Themes\Http\Resources\ThemeCategoryResource;
use Modules\Themes\Http\Resources\ThemePublisherResource;
use Modules\Themes\Models\ThemeCategory;
use Modules\Themes\Models\ThemePublisher;
use Modules\Themes\Services\ThemeCatalogAdminService;

final class PlatformThemeTaxonomyController extends Controller
{
    public function publishers(PaginatedIndexRequest $request, ThemeCatalogAdminService $service): JsonResponse
    {
        return ThemePublisherResource::collection($service->publishers($this->user($request), $request->perPage()))->response();
    }

    public function storePublisher(PlatformThemePublisherRequest $request, ThemeCatalogAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemePublisherResource($service->savePublisher($this->user($request), $request->validated()))], 201);
    }

    public function updatePublisher(PlatformThemePublisherRequest $request, ThemePublisher $themePublisher, ThemeCatalogAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemePublisherResource($service->savePublisher($this->user($request), $request->validated(), $themePublisher))]);
    }

    public function categories(PaginatedIndexRequest $request, ThemeCatalogAdminService $service): JsonResponse
    {
        return ThemeCategoryResource::collection($service->categories($this->user($request), $request->perPage()))->response();
    }

    public function storeCategory(PlatformThemeCategoryRequest $request, ThemeCatalogAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeCategoryResource($service->saveCategory($this->user($request), $request->validated()))], 201);
    }

    public function updateCategory(PlatformThemeCategoryRequest $request, ThemeCategory $themeCategory, ThemeCatalogAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeCategoryResource($service->saveCategory($this->user($request), $request->validated(), $themeCategory))]);
    }

    private function user(PaginatedIndexRequest|PlatformThemePublisherRequest|PlatformThemeCategoryRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
