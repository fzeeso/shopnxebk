<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ModifierLibraryCategoryResource;
use Modules\Catalog\Services\ModifierLibraryService;

final class ModifierLibraryCategoryController extends Controller
{
    public function index(Request $request, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => ModifierLibraryCategoryResource::collection($service->categories($this->user($request)))]);
    }

    public function store(Request $request, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => new ModifierLibraryCategoryResource($service->createCategory($this->user($request), $request->all()))], 201);
    }

    public function update(Request $request, string $category, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => new ModifierLibraryCategoryResource($service->updateCategory($this->user($request), $category, $request->all()))]);
    }

    public function destroy(Request $request, string $category, ModifierLibraryService $service): JsonResponse
    {
        $service->deleteCategory($this->user($request), $category);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
