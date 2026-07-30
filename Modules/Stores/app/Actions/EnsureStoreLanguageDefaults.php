<?php

declare(strict_types=1);

namespace Modules\Stores\Actions;

use Modules\Settings\Models\Language;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreLanguage;

final class EnsureStoreLanguageDefaults
{
    public function ensure(): void
    {
        $fallback = Language::query()->where('locale', 'en')->firstOrFail();

        Store::query()
            ->select(['id', 'language_code'])
            ->eachById(function (Store $store) use ($fallback): void {
                if (StoreLanguage::query()->where('store_id', $store->getKey())->exists()) {
                    return;
                }

                $locale = str_replace('-', '_', (string) $store->language_code);
                $language = Language::query()->where('locale', $locale)->first() ?? $fallback;

                StoreLanguage::query()->create([
                    'store_id' => $store->getKey(),
                    'language_id' => $language->getKey(),
                    'is_default' => true,
                    'is_active' => true,
                ]);
            });
    }
}
