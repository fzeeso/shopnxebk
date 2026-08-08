<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Models\Store;

/** @extends JsonResource<Store> */
final class StoreSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = $this->resource->storeSettings;
        $localeSettings = $this->resource->localeSettings;
        $preferences = is_array($this->settings) ? $this->settings : [];

        if ($localeSettings !== null) {
            $preferences = array_replace($preferences, [
                'date_format' => $localeSettings->date_format,
                'time_format' => $localeSettings->time_format,
                'weight_unit' => $localeSettings->weight_unit,
                'dimension_unit' => $localeSettings->dimension_unit,
            ]);
        }

        return [
            'store_id' => $this->public_id,
            'currency_code' => $this->currency_code,
            'language_code' => $this->language_code,
            'timezone' => $this->timezone,
            'country_code' => $this->country_code,
            'contact_email' => $settings?->contact_email,
            'contact_phone' => $settings?->contact_phone,
            'store_country_code' => $settings?->store_country_code,
            'store_state' => $settings?->store_state,
            'store_city' => $settings?->store_city,
            'store_zip' => $settings?->store_zip,
            'store_address_1' => $settings?->store_address_1,
            'store_address_2' => $settings?->store_address_2,
            'auto_store_translation_flag' => (bool) ($settings?->auto_store_translation_flag ?? false),
            'is_searchable_on_platform_flag' => (bool) ($settings?->is_searchable_on_platform_flag ?? false),
            'preferences' => $preferences,
            'capabilities' => [
                'ai' => $this->is_ai_enabled,
                'pos' => $this->is_pos_enabled,
                'b2b' => $this->is_b2b_enabled,
                'marketplace' => $this->is_marketplace_enabled,
            ],
        ];
    }
}
