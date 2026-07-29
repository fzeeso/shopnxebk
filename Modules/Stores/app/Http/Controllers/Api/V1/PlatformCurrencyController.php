<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Http\Requests\CreateCurrencyRequest;
use Modules\Stores\Http\Requests\UpdateCurrencyRequest;
use Modules\Stores\Http\Resources\CurrencyResource;
use Modules\Stores\Models\Currency;
use Modules\Stores\Services\CurrencyCatalogService;

final class PlatformCurrencyController extends Controller
{
    public function index(Request $request, CurrencyCatalogService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => CurrencyResource::collection($service->listPlatform($user)),
        ]);
    }

    public function store(CreateCurrencyRequest $request, CurrencyCatalogService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currency = $service->createPlatform($user, $request->validated());

        return response()->json(['data' => new CurrencyResource($currency)], 201);
    }

    public function update(
        UpdateCurrencyRequest $request,
        string $currency,
        CurrencyCatalogService $service,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $model = Currency::query()->where('public_id', $currency)->firstOrFail();
        $updated = $service->updatePlatform($user, $model, $request->validated());

        return response()->json(['data' => new CurrencyResource($updated)]);
    }
}
