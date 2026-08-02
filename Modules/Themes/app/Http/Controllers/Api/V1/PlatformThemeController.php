<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Themes\Http\Requests\ListThemesRequest;
use Modules\Themes\Http\Requests\PlatformThemeRequest;
use Modules\Themes\Http\Resources\ThemeResource;
use Modules\Themes\Models\Theme;
use Modules\Themes\Services\ThemeCatalogAdminService;

final class PlatformThemeController extends Controller
{
    public function index(ListThemesRequest $request, ThemeCatalogAdminService $service): JsonResponse
    {
        return ThemeResource::collection($service->list($this->user($request), $request->validated(), $request->perPage()))->response();
    }

    public function store(PlatformThemeRequest $request, ThemeCatalogAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeResource($service->create($this->user($request), $request->validated()))], 201);
    }

    public function show(PlatformThemeRequest $request, Theme $theme, ThemeCatalogAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeResource($service->view($this->user($request), $theme))]);
    }

    public function update(PlatformThemeRequest $request, Theme $theme, ThemeCatalogAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeResource($service->update($this->user($request), $theme, $request->validated()))]);
    }

    private function user(PlatformThemeRequest|ListThemesRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
