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
        return [
            'store_id' => $this->public_id,
            'currency_code' => $this->currency_code,
            'language_code' => $this->language_code,
            'timezone' => $this->timezone,
            'country_code' => $this->country_code,
            'preferences' => $this->settings ?? [],
            'capabilities' => [
                'ai' => $this->is_ai_enabled,
                'pos' => $this->is_pos_enabled,
                'b2b' => $this->is_b2b_enabled,
                'marketplace' => $this->is_marketplace_enabled,
            ],
        ];
    }
}
