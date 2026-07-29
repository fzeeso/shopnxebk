<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Billing\Http\Requests\UpsertPlanFeatureRequest;
use Modules\Billing\Http\Resources\PlanFeatureResource;
use Modules\Billing\Models\Feature;
use Modules\Billing\Models\Plan;
use Modules\Billing\Services\PlanFeatureAdminService;

final class PlatformPlanFeatureController extends Controller
{
    public function upsert(
        UpsertPlanFeatureRequest $request,
        string $plan,
        string $feature,
        PlanFeatureAdminService $service,
    ): JsonResponse {
        $assignment = $service->upsert(
            $this->user($request),
            $this->plan($plan),
            $this->feature($feature),
            $request->validated(),
        );

        return response()->json(['data' => new PlanFeatureResource($assignment)]);
    }

    public function destroy(
        Request $request,
        string $plan,
        string $feature,
        PlanFeatureAdminService $service,
    ): JsonResponse {
        $service->remove(
            $this->user($request),
            $this->plan($plan),
            $this->feature($feature),
        );

        return response()->json(null, 204);
    }

    private function plan(string $publicId): Plan
    {
        return Plan::query()->where('public_id', $publicId)->firstOrFail();
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
