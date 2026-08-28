<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\CustomFieldDefinitionWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\CustomFieldDefinitionResource;
use Modules\Catalog\Services\CustomFieldManagementService;

final class CustomFieldDefinitionController extends Controller
{
    public function index(Request $request, CustomFieldManagementService $service): JsonResponse
    {
        $filter = [];
        foreach ([
            'search' => 'search',
            'product_type' => 'productType',
            'field_key' => 'fieldKey',
            'field_type' => 'fieldType',
            'is_required' => 'isRequired',
            'is_filterable' => 'isFilterable',
        ] as $rest => $internal) {
            if ($request->query->has($rest)) {
                $filter[$internal] = $request->query($rest);
            }
        }
        $sortBy = match ($request->query('sort_by', 'position')) {
            'field_key' => 'fieldKey',
            'created_at' => 'createdAt',
            'updated_at' => 'updatedAt',
            default => 'position',
        };

        return CustomFieldDefinitionResource::collection($service->listDefinitions($this->user($request), [
            'page' => $request->integer('page', 1),
            'perPage' => $request->integer('per_page', 25),
            'filter' => $filter,
            'sortBy' => $sortBy,
            'sortDirection' => $request->query('sort_direction', 'asc'),
        ]))->response();
    }

    public function store(
        CustomFieldDefinitionWriteRequest $request,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomFieldDefinitionResource(
                $service->createDefinition($this->user($request), $request->validated()),
            ),
        ], 201);
    }

    public function show(
        Request $request,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomFieldDefinitionResource(
                $service->showDefinition($this->user($request), $definition),
            ),
        ]);
    }

    public function update(
        CustomFieldDefinitionWriteRequest $request,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomFieldDefinitionResource(
                $service->updateDefinition($this->user($request), $definition, $request->validated()),
            ),
        ]);
    }

    public function destroy(
        Request $request,
        string $definition,
        CustomFieldManagementService $service,
    ): JsonResponse {
        $service->deleteDefinition($this->user($request), $definition);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
