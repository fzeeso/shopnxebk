<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Themes\Http\Requests\InstallStoreThemeRequest;
use Modules\Themes\Http\Requests\ListThemesRequest;
use Modules\Themes\Http\Requests\UpdateStoreThemeRequest;
use Modules\Themes\Http\Resources\StoreThemeResource;
use Modules\Themes\Http\Resources\ThemeResource;
use Modules\Themes\Models\StoreTheme;
use Modules\Themes\Models\Theme;
use Modules\Themes\Services\StoreThemeService;

final class StoreThemeController extends Controller
{
    public function index(PaginatedIndexRequest $request, StoreThemeService $service): JsonResponse
    {
        return StoreThemeResource::collection($service->installed($this->user($request), $request->perPage()))->response();
    }

    public function marketplace(ListThemesRequest $request, StoreThemeService $service): JsonResponse
    {
        return ThemeResource::collection($service->marketplace($this->user($request), $request->perPage(), $request->validated('search')))->response();
    }

    public function install(InstallStoreThemeRequest $request, StoreThemeService $service): JsonResponse
    {
        $theme = Theme::query()->where('public_id', $request->validated('theme_id'))->firstOrFail();

        return response()->json(['data' => new StoreThemeResource($service->install(
            $this->user($request),
            $theme,
            $request->validated('name'),
            (bool) $request->validated('as_trial', false),
        ))], 201);
    }

    public function update(UpdateStoreThemeRequest $request, StoreTheme $storeTheme, StoreThemeService $service): JsonResponse
    {
        return response()->json(['data' => new StoreThemeResource($service->update($this->user($request), $storeTheme, $request->validated()))]);
    }

    public function duplicate(Request $request, StoreTheme $storeTheme, StoreThemeService $service): JsonResponse
    {
        return response()->json(['data' => new StoreThemeResource($service->duplicate($this->user($request), $storeTheme))], 201);
    }

    public function publish(Request $request, StoreTheme $storeTheme, StoreThemeService $service): JsonResponse
    {
        return response()->json(['data' => new StoreThemeResource($service->publish($this->user($request), $storeTheme))]);
    }

    public function destroy(Request $request, StoreTheme $storeTheme, StoreThemeService $service): JsonResponse
    {
        $service->delete($this->user($request), $storeTheme);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
