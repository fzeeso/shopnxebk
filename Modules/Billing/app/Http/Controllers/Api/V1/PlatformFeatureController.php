<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Billing\Http\Requests\CreateFeatureRequest;
use Modules\Billing\Http\Requests\UpdateFeatureRequest;
use Modules\Billing\Http\Resources\FeatureResource;
use Modules\Billing\Models\Feature;
use Modules\Billing\Services\FeatureAdminService;

final class PlatformFeatureController extends Controller
{
    public function index(PaginatedIndexRequest $request, FeatureAdminService $service): JsonResponse
    {
        return FeatureResource::collection(
            $service->list($this->user($request), $request->perPage()),
        )->response();
    }

    public function store(CreateFeatureRequest $request, FeatureAdminService $service): JsonResponse
    {
        $feature = $service->create($this->user($request), $request->validated());

        return response()->json(['data' => new FeatureResource($feature)], 201);
    }

    public function update(
        UpdateFeatureRequest $request,
        string $feature,
        FeatureAdminService $service,
    ): JsonResponse {
        $updated = $service->update($this->user($request), $this->feature($feature), $request->validated());

        return response()->json(['data' => new FeatureResource($updated)]);
    }

    public function destroy(Request $request, string $feature, FeatureAdminService $service): JsonResponse
    {
        $service->delete($this->user($request), $this->feature($feature));

        return response()->json(null, 204);
    }

    private function feature(string $publicId): Feature
    {
        return Feature::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
