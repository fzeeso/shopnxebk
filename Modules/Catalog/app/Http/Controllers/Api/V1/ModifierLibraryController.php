<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ModifierDefinitionResource;
use Modules\Catalog\Services\ModifierLibraryService;

final class ModifierLibraryController extends Controller
{
    public function index(Request $request, ModifierLibraryService $service): JsonResponse
    {
        return ModifierDefinitionResource::collection($service->list($this->user($request), $request->only([
            'page', 'per_page', 'search', 'type', 'category_id', 'is_active',
        ])))->response();
    }

    public function store(Request $request, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => new ModifierDefinitionResource($service->create($this->user($request), $request->all()))], 201);
    }

    public function show(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => new ModifierDefinitionResource($service->show($this->user($request), $modifier))]);
    }

    public function update(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => new ModifierDefinitionResource($service->update($this->user($request), $modifier, $request->all()))]);
    }

    public function replaceTranslations(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        return $this->replaceCollection($request, $modifier, 'translations', $service);
    }

    public function replaceValues(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        return $this->replaceCollection($request, $modifier, 'values', $service);
    }

    public function replaceValidationRules(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        return $this->replaceCollection($request, $modifier, 'validation_rules', $service);
    }

    public function replacePriceAdjustments(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        return $this->replaceCollection($request, $modifier, 'price_adjustments', $service);
    }

    public function destroy(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        $service->delete($this->user($request), $modifier);

        return response()->json(null, 204);
    }

    public function active(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        return response()->json(['data' => new ModifierDefinitionResource($service->setActive($this->user($request), $modifier, (bool) $data['is_active']))]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function replaceCollection(Request $request, string $modifier, string $key, ModifierLibraryService $service): JsonResponse
    {
        $data = $request->validate([$key => ['required', 'array']]);

        return response()->json(['data' => new ModifierDefinitionResource(
            $service->update($this->user($request), $modifier, [$key => $data[$key]]),
        )]);
    }
}
