<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Settings\Http\Requests\CreateCurrencyRequest;
use Modules\Settings\Http\Requests\UpdateCurrencyRequest;
use Modules\Settings\Http\Resources\CurrencyResource;
use Modules\Settings\Models\Currency;
use Modules\Settings\Services\CurrencyCatalogService;

final class CurrencyController extends Controller
{
    public function index(PaginatedIndexRequest $request, CurrencyCatalogService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return CurrencyResource::collection(
            $service->listPlatform($user, $request->perPage()),
        )->response();
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
