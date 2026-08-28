<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use App\Http\Requests\SharedProductOptionWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Catalog\Http\Resources\SharedProductOptionResource;
use Modules\Catalog\Services\SharedProductOptionService;

final class SharedProductOptionController extends Controller
{
    public function index(Request $request, SharedProductOptionService $service): JsonResponse
    {
        return SharedProductOptionResource::collection($service->list($this->user($request), $request->only([
            'page', 'per_page', 'search', 'type',
        ])))->response();
    }

    public function store(SharedProductOptionWriteRequest $request, SharedProductOptionService $service): JsonResponse
    {
        return response()->json([
            'data' => new SharedProductOptionResource($service->create($this->user($request), $request->validated())),
        ], 201);
    }

    public function show(Request $request, string $option, SharedProductOptionService $service): JsonResponse
    {
        return response()->json([
            'data' => new SharedProductOptionResource($service->show($this->user($request), $option)),
        ]);
    }

    public function update(
        SharedProductOptionWriteRequest $request,
        string $option,
        SharedProductOptionService $service,
    ): JsonResponse {
        return response()->json([
            'data' => new SharedProductOptionResource(
                $service->update($this->user($request), $option, $request->validated()),
            ),
        ]);
    }

    public function destroy(Request $request, string $option, SharedProductOptionService $service): JsonResponse
    {
        $service->delete($this->user($request), $option);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
