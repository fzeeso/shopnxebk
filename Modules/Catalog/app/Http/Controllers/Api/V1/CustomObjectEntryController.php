<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Requests\CustomObjectEntryWriteRequest;
use Modules\Catalog\Http\Requests\CustomObjectIndexRequest;
use Modules\Catalog\Http\Resources\CustomObjectEntryOptionResource;
use Modules\Catalog\Http\Resources\CustomObjectEntryResource;
use Modules\Catalog\Services\CustomObjectEntryService;

final class CustomObjectEntryController extends Controller
{
    public function index(
        CustomObjectIndexRequest $request,
        string $type,
        CustomObjectEntryService $service,
    ): JsonResponse {
        return CustomObjectEntryResource::collection(
            $service->listEntries($this->user($request), $type, $request->validated()),
        )->response();
    }

    public function options(
        CustomObjectIndexRequest $request,
        string $type,
        CustomObjectEntryService $service,
    ): JsonResponse {
        $filters = [...$request->validated(), 'status' => 'active'];

        return CustomObjectEntryOptionResource::collection(
            $service->listEntries($this->user($request), $type, $filters),
        )->response();
    }

    public function store(
        CustomObjectEntryWriteRequest $request,
        string $type,
        CustomObjectEntryService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomObjectEntryResource(
                $service->createEntry($this->user($request), $type, $request->validated()),
            ),
        ], 201);
    }

    public function show(Request $request, string $entry, CustomObjectEntryService $service): JsonResponse
    {
        return response()->json([
            'data' => new CustomObjectEntryResource($service->showEntry($this->user($request), $entry)),
        ]);
    }

    public function update(
        CustomObjectEntryWriteRequest $request,
        string $entry,
        CustomObjectEntryService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new CustomObjectEntryResource(
                $service->updateEntry($this->user($request), $entry, $request->validated()),
            ),
        ]);
    }

    public function destroy(Request $request, string $entry, CustomObjectEntryService $service): JsonResponse
    {
        $service->deleteEntry($this->user($request), $entry);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
