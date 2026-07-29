<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Billing\Http\Requests\CreatePlanRequest;
use Modules\Billing\Http\Requests\UpdatePlanRequest;
use Modules\Billing\Http\Resources\PlanResource;
use Modules\Billing\Models\Plan;
use Modules\Billing\Services\PlanAdminService;

final class PlatformPlanController extends Controller
{
    public function index(Request $request, PlanAdminService $service): JsonResponse
    {
        return response()->json([
            'data' => PlanResource::collection($service->list($this->user($request))),
        ]);
    }

    public function store(CreatePlanRequest $request, PlanAdminService $service): JsonResponse
    {
        $plan = $service->create($this->user($request), $request->validated());

        return response()->json(['data' => new PlanResource($plan)], 201);
    }

    public function show(Request $request, string $plan, PlanAdminService $service): JsonResponse
    {
        return response()->json([
            'data' => new PlanResource($service->view($this->user($request), $this->plan($plan))),
        ]);
    }

    public function update(
        UpdatePlanRequest $request,
        string $plan,
        PlanAdminService $service,
    ): JsonResponse {
        $updated = $service->update($this->user($request), $this->plan($plan), $request->validated());

        return response()->json(['data' => new PlanResource($updated)]);
    }

    public function destroy(Request $request, string $plan, PlanAdminService $service): JsonResponse
    {
        $service->delete($this->user($request), $this->plan($plan));

        return response()->json(null, 204);
    }

    private function plan(string $publicId): Plan
    {
        return Plan::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
