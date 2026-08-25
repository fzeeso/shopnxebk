<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\ModifierValueResource;
use Modules\Catalog\Services\ModifierLibraryService;

final class ModifierValueController extends Controller
{
    public function index(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => ModifierValueResource::collection($service->values($this->user($request), $modifier))]);
    }

    public function store(Request $request, string $modifier, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => new ModifierValueResource(
            $service->createValue($this->user($request), $modifier, $request->all()),
        )], 201);
    }

    public function show(Request $request, string $modifier, string $value, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => new ModifierValueResource(
            $service->showValue($this->user($request), $modifier, $value),
        )]);
    }

    public function update(Request $request, string $modifier, string $value, ModifierLibraryService $service): JsonResponse
    {
        return response()->json(['data' => new ModifierValueResource(
            $service->updateValue($this->user($request), $modifier, $value, $request->all()),
        )]);
    }

    public function destroy(Request $request, string $modifier, string $value, ModifierLibraryService $service): JsonResponse
    {
        $service->deleteValue($this->user($request), $modifier, $value);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
