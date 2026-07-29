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

    public function __construct(private StoreAccessService $access) {}

    public function view(User $user, Store $store): Store
    {
        $this->access->ensureCanView($user, $store);

        return $store->refresh();
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Store $store, array $data): Store
    {
        $this->access->ensureCanManage($user, $store);

        return DB::transaction(function () use ($store, $data): Store {
            $attributes = Arr::only($data, self::LOCALE_FIELDS);

            if (isset($data['preferences']) && is_array($data['preferences'])) {
                $attributes['settings'] = array_replace(
                    is_array($store->settings) ? $store->settings : [],
                    $data['preferences'],
                );
            }

            if ($attributes !== []) {
                $store->fill($attributes)->save();
            }

            return $store->refresh();
        });
    }
}
