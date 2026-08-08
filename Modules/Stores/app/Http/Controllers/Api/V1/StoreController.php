<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Http\Requests\CreateStoreRequest;
use Modules\Stores\Http\Requests\UpdateStoreProfileRequest;
use Modules\Stores\Http\Requests\UpdateStoreSettingsRequest;
use Modules\Stores\Http\Resources\StoreResource;
use Modules\Stores\Http\Resources\StoreSettingsResource;
use Modules\Stores\Models\Store;
use Modules\Stores\Services\CreateStoreService;
use Modules\Stores\Services\StoreAccessService;
use Modules\Stores\Services\StoreDashboardUrl;
use Modules\Stores\Services\UpdateStoreProfileService;
use Modules\Stores\Services\ViewStoreService;

final class StoreController extends Controller
{
    private const LOCALE_FIELDS = ['currency_code', 'language_code', 'timezone', 'country_code'];

    private const NORMALIZED_SETTING_FIELDS = [
        'contact_email',
        'contact_phone',
        'store_country_code',
        'store_state',
        'store_city',
        'store_zip',
        'store_address_1',
        'store_address_2',
        'auto_store_translation_flag',
        'is_searchable_on_platform_flag',
    ];

    private const LOCALE_SETTING_FIELDS = ['date_format', 'time_format', 'weight_unit', 'dimension_unit'];

    public function store(
        CreateStoreRequest $request,
        CreateStoreService $service,
        StoreDashboardUrl $dashboardUrl,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $store = $service->create($user, $request->validated());

        return response()->json([
            'data' => new StoreResource($store),
            'dashboard_url' => $dashboardUrl->for($store),
        ], 201);
    }

    public function show(Request $request, StoreContext $context, ViewStoreService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => new StoreResource($service->view($user, $context->require()))]);
    }

    public function updateProfile(
        UpdateStoreProfileRequest $request,
        StoreContext $context,
        UpdateStoreProfileService $service,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $store = $service->update($user, $context->require(), $request->validated());

        return response()->json(['data' => new StoreResource($store)]);
    }

    public function settings(Request $request, StoreContext $context, StoreAccessService $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store = $context->require();
        $access->ensureCanView($user, $store);

        return response()->json(['data' => new StoreSettingsResource(
            $store->refresh()->load(['storeSettings', 'localeSettings']),
        )]);
    }

    public function updateSettings(
        UpdateStoreSettingsRequest $request,
        StoreContext $context,
        StoreAccessService $access,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $store = $context->require();
        $access->ensureCanManage($user, $store);
        $store = $this->persistSettings($store, $request->validated());

        return response()->json(['data' => new StoreSettingsResource($store)]);
    }

    /** @param array<string, mixed> $data */
    private function persistSettings(Store $store, array $data): Store
    {
        return DB::transaction(function () use ($store, $data): Store {
            $attributes = Arr::only($data, self::LOCALE_FIELDS);
            $normalizedSettings = Arr::only($data, self::NORMALIZED_SETTING_FIELDS);

            if (isset($data['preferences']) && is_array($data['preferences'])) {
                $attributes['settings'] = array_replace(
                    is_array($store->settings) ? $store->settings : [],
                    $data['preferences'],
                );

                if (array_key_exists('support_email', $data['preferences'])) {
                    $normalizedSettings['contact_email'] = $data['preferences']['support_email'];
                }
                if (array_key_exists('weight_unit', $data['preferences'])) {
                    $normalizedSettings['weight_unit'] = $data['preferences']['weight_unit'];
                }
                if (array_key_exists('order_prefix', $data['preferences'])) {
                    $normalizedSettings['order_number_prefix'] = $data['preferences']['order_prefix'];
                }

                $localeSettings = Arr::only($data['preferences'], self::LOCALE_SETTING_FIELDS);
                if ($localeSettings !== []) {
                    $store->localeSettings()->updateOrCreate([], $localeSettings);
                }
            }

            if ($attributes !== []) {
                $store->fill($attributes)->save();
            }

            if ($normalizedSettings !== []) {
                $store->storeSettings()->updateOrCreate([], $normalizedSettings);
            }

            return $store->refresh()->load(['storeSettings', 'localeSettings']);
        });
    }
}
