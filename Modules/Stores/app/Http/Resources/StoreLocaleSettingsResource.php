<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Models\Store;

/** @extends JsonResource<Store> */
final class StoreLocaleSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $this->resource->localeSettings;
        $preferences = is_array($this->settings) ? $this->settings : [];

        return [
            'store_id' => $this->public_id,
            'currency_code' => $this->currency_code,
            'language_code' => $this->language_code,
            'timezone' => $this->timezone,
            'country_code' => $this->country_code,
            'date_format' => $locale?->date_format ?? $preferences['date_format'] ?? 'Y-m-d',
            'time_format' => $locale?->time_format ?? $preferences['time_format'] ?? '24h',
            'week_starts_on' => $locale?->week_starts_on ?? 'monday',
            'weight_unit' => $locale?->weight_unit ?? $preferences['weight_unit'] ?? 'kg',
            'dimension_unit' => $locale?->dimension_unit ?? $preferences['dimension_unit'] ?? 'cm',
            'decimal_places' => $locale?->decimal_places ?? 2,
            'decimal_separator' => $locale?->decimal_separator ?? 'dot',
            'thousands_separator' => $locale?->thousands_separator ?? 'comma',
            'managed' => [
                'character_set' => 'UTF-8',
                'daylight_saving' => 'automatic',
            ],
            'updated_at' => $locale?->updated_at?->toISOString(),
        ];
    }
}
