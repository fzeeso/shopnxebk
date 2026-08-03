<?php

declare(strict_types=1);

namespace Modules\Stores\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PlatformStoreLocaleSettingsService
{
    private const STORE_FIELDS = [
        'currency_code',
        'language_code',
        'timezone',
        'country_code',
    ];

    private const LOCALE_SETTING_FIELDS = [
        'date_format',
        'time_format',
        'week_starts_on',
        'weight_unit',
        'dimension_unit',
        'decimal_places',
        'decimal_separator',
        'thousands_separator',
    ];

    private const LEGACY_PREFERENCE_FIELDS = [
        'date_format',
        'time_format',
        'weight_unit',
        'dimension_unit',
    ];

    public function __construct(private PlatformStoreAccessService $access) {}

    public function view(User $actor, string $publicId): Store
    {
        $this->access->ensureCanManageStores($actor);
        $store = $this->find($publicId);
        $localeSettings = $store->localeSettings()->firstOrNew();
        $store->setRelation('localeSettings', $localeSettings);

        return $store;
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, string $publicId, array $data): Store
    {
        $this->access->ensureCanManageStores($actor);
        $store = $this->find($publicId);

        return DB::transaction(function () use ($data, $store): Store {
            $storeFields = Arr::only($data, self::STORE_FIELDS);
            $localeFields = Arr::only($data, self::LOCALE_SETTING_FIELDS);

            if ($storeFields !== []) {
                $store->fill($storeFields)->save();
            }

            if ($localeFields !== []) {
                $store->localeSettings()->updateOrCreate([], $localeFields);
            }

            $legacyPreferences = Arr::only($data, self::LEGACY_PREFERENCE_FIELDS);
            if ($legacyPreferences !== []) {
                $store->settings = array_replace(
                    is_array($store->settings) ? $store->settings : [],
                    $legacyPreferences,
                );
                $store->save();
            }

            if (array_key_exists('weight_unit', $data)) {
                $store->storeSettings()->updateOrCreate([], [
                    'weight_unit' => $data['weight_unit'],
                ]);
            }

            $store->refresh();
            $localeSettings = $store->localeSettings()->firstOrNew();
            $store->setRelation('localeSettings', $localeSettings);

            return $store;
        });
    }

    private function find(string $publicId): Store
    {
        $store = Store::query()->where('public_id', $publicId)->first();
        if (! $store instanceof Store) {
            throw new NotFoundHttpException('Store not found.');
        }

        return $store;
    }
}
