<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\CustomFieldOptionWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\CustomFieldOptionResource;
use Modules\Catalog\Services\CustomFieldManagementService;

final class CustomFieldOptionController extends Controller
{
    public function index(
        Request $request,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => CustomFieldOptionResource::collection(
                $service->listOptions($this->user($request), $definition),
            ),
        ]);
    }

    public function store(
        CustomFieldOptionWriteRequest $request,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomFieldOptionResource(
                $service->createOption($this->user($request), $definition, $request->validated()),
            ),
        ], 201);
    }

    public function show(
        Request $request,
        string $definition,
        string $option,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomFieldOptionResource(
                $service->showOption($this->user($request), $definition, $option),
            ),
        ]);
    }

    public function update(
        CustomFieldOptionWriteRequest $request,
        string $definition,
        string $option,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomFieldOptionResource(
                $service->updateOption($this->user($request), $definition, $option, $request->validated()),
            ),
        ]);
    }

    public function destroy(
        Request $request,
        string $definition,
        string $option,
        CustomFieldManagementService $service,
    ): JsonResponse {
        $service->deleteOption($this->user($request), $definition, $option);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
