<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;

final readonly class StoreSettingsService
{
    private const LOCALE_FIELDS = [
        'currency_code',
        'language_code',
        'timezone',
        'country_code',
    ];

    private const NORMALIZED_SETTING_FIELDS = [
        'contact_email',
        'contact_phone',
        'store_country_code',
        'store_state',
        'store_city',
        'store_zip',
        'store_address_1',
        'store_address_2',
    ];

    private const LOCALE_SETTING_FIELDS = [
        'date_format',
        'time_format',
        'weight_unit',
        'dimension_unit',
    ];

    public function __construct(private StoreAccessService $access) {}

    public function view(User $user, Store $store): Store
    {
        $this->access->ensureCanView($user, $store);

        return $store->refresh()->load(['storeSettings', 'localeSettings']);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Store $store, array $data): Store
    {
        $this->access->ensureCanManage($user, $store);

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
